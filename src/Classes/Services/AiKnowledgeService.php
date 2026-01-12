<?php

namespace gutesio\OperatorBundle\Classes\Services;

use Contao\Database;
use Contao\System;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Curl\CurlPostRequest;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;

class AiKnowledgeService
{
    private $aiChatbotService;

    public function __construct(AiChatbotService $aiChatbotService)
    {
        $this->aiChatbotService = $aiChatbotService;
    }

    public function exportAllData(): array
    {
        $db = Database::getInstance();
        $config = $this->aiChatbotService->getTableConfig();
        $allData = [];

        foreach ($config as $tableConfig) {
            $table = $tableConfig['table'];
            $baseTable = $tableConfig['baseTable'] ?? null;
            $fields = $tableConfig['fields'];
            
            $queryTable = $baseTable ?: $table;
            $sql = "SELECT * FROM `" . $queryTable . "` WHERE published='1'";
            if (isset($tableConfig['where'])) {
                $sql .= " AND (" . $tableConfig['where'] . ")";
            }

            try {
                $objRows = $db->prepare($sql)->execute();
                while ($objRows->next()) {
                    $item = [
                        'id' => $objRows->uuid,
                        'table' => $table
                    ];

                    $fieldArray = array_map('trim', explode(',', $fields));
                    foreach ($fieldArray as $field) {
                        $column = $field;
                        if (strpos($field, '.') !== false) {
                            $parts = explode('.', $field);
                            $column = end($parts);
                        }
                        if (isset($objRows->$column)) {
                            $val = $objRows->$column;
                            if (in_array($column, ['beginDate', 'endDate', 'dateOfBirth']) && $val && is_numeric($val)) {
                                $val = date('d.m.Y', (int)$val);
                            } elseif (in_array($column, ['beginTime', 'endTime', 'startTime']) && $val && is_numeric($val)) {
                                $val = date('H:i', (int)$val);
                            }
                            $item[$column] = $val;
                        }
                    }
                    
                    // Specific fields from base table if they exist
                    if ($baseTable) {
                        // We might need to join the child table to get the child specific fields
                        // but for the knowledge base, often the base info + what's in the tableConfig is enough.
                        // However, to be thorough:
                        $childSql = "SELECT * FROM `" . $table . "` WHERE childId=?";
                        $objChild = $db->prepare($childSql)->execute($objRows->uuid);
                        if ($objChild->next()) {
                            foreach ($fieldArray as $field) {
                                if (strpos($field, 't2.') !== false) {
                                    $column = str_replace('t2.', '', $field);
                                    if (isset($objChild->$column)) {
                                        $val = $objChild->$column;
                                        if (in_array($column, ['beginDate', 'endDate', 'dateOfBirth']) && $val && is_numeric($val)) {
                                            $val = date('d.m.Y', (int)$val);
                                        } elseif (in_array($column, ['beginTime', 'endTime', 'startTime']) && $val && is_numeric($val)) {
                                            $val = date('H:i', (int)$val);
                                        }
                                        $item[$column] = $val;
                                    }
                                }
                            }
                        }
                    }

                    $allData[] = $item;
                }
            } catch (\Throwable $e) {
                C4gLogModel::addLogEntry("operator", "AI Knowledge Export Error for table $table: " . $e->getMessage());
            }
        }

        return $allData;
    }

    public function runKnowledgeUpdate(): string
    {
        $settings = GutesioOperatorSettingsModel::findSettings();
        if (!$settings || !$settings->aiEnabled || !$settings->aiApiKey) {
            return "KI nicht aktiviert oder API-Key fehlt.";
        }

        $apiKey = $settings->aiApiKey;
        $allData = $this->exportAllData();
        
        if (empty($allData)) {
            return "Keine Daten zum Exportieren gefunden.";
        }

        $jsonContent = json_encode($allData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tempFile = tempnam(sys_get_temp_dir(), 'ai_knowledge_');
        file_put_contents($tempFile, $jsonContent);

        try {
            // 1. Upload file to OpenAI
            $fileId = $this->uploadFileToOpenAi($apiKey, $tempFile);
            if (!$fileId) {
                return "Fehler beim Hochladen der Datei zu OpenAI.";
            }

            // 2. Add to Vector Store
            $vectorStoreId = $settings->aiVectorStoreId;
            if (!$vectorStoreId) {
                $vectorStoreId = $this->createVectorStore($apiKey, "Gutes.digital Knowledge Base");
                if ($vectorStoreId) {
                    $settings->aiVectorStoreId = $vectorStoreId;
                    $settings->save();
                }
            }

            if ($vectorStoreId) {
                $this->addFileToVectorStore($apiKey, $vectorStoreId, $fileId);
            }

            // 3. Update Assistant if ID is set
            if ($settings->aiAssistantId) {
                $this->updateAssistantWithVectorStore($apiKey, $settings->aiAssistantId, $vectorStoreId);
            }

            unlink($tempFile);
            return "Erfolgreich: " . count($allData) . " Datensätze an KI übertragen. File-ID: $fileId";

        } catch (\Throwable $e) {
            if (file_exists($tempFile)) unlink($tempFile);
            return "Fehler beim Knowledge Update: " . $e->getMessage();
        }
    }

    private function uploadFileToOpenAi(string $apiKey, string $filePath): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/files');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        
        $cfile = new \CURLFile($filePath, 'application/json', 'knowledge_base.json');
        $post = ['purpose' => 'assistants', 'file' => $cfile];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data['id'] ?? null;
    }

    private function createVectorStore(string $apiKey, string $name): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/vector_stores');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => $name]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'OpenAI-Beta: assistants=v2'
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data['id'] ?? null;
    }

    private function addFileToVectorStore(string $apiKey, string $vectorStoreId, string $fileId): bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/vector_stores/$vectorStoreId/file_batches");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['file_ids' => [$fileId]]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'OpenAI-Beta: assistants=v2'
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return isset($data['id']);
    }

    private function updateAssistantWithVectorStore(string $apiKey, string $assistantId, string $vectorStoreId): bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/assistants/$assistantId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'tools' => [['type' => 'file_search']],
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [$vectorStoreId]
                ]
            ]
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'OpenAI-Beta: assistants=v2'
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return isset($data['id']);
    }
}
