<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.
 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */

namespace gutesio\OperatorBundle\Classes\Services;

use Contao\Database;
use Contao\PageModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Curl\CurlPostRequest;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;

class AiChatbotService
{
    /**
     * @var \con4gis\MapsBundle\Classes\Services\AreaService
     */
    private $areaService;

    public function __construct(\con4gis\MapsBundle\Classes\Services\AreaService $areaService)
    {
        $this->areaService = $areaService;
    }

    private function getTableConfig(): array
    {
        $config = [
            [
                'table' => 'tl_gutesio_data_element',
                'fields' => 'name, description, locationStreet, locationStreetNumber, locationZip, locationCity, phone, email, website, opening_hours'
            ],
            [
                'table' => 'tl_gutesio_data_child_product',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.price, t2.strikePrice, t2.brand'
            ],
            [
                'table' => 'tl_gutesio_data_child_event',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.beginDate, t2.endDate, t2.beginTime, t2.endTime, t2.eventPrice'
            ],
            [
                'table' => 'tl_gutesio_data_child_job',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.applicationContactEMail, t2.applicationContactPhone, t2.workHours'
            ],
            [
                'table' => 'tl_gutesio_data_child_voucher',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.credit'
            ],
            [
                'table' => 'tl_gutesio_data_child_person',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.dateOfBirth'
            ],
            [
                'table' => 'tl_gutesio_data_child_realestate',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription'
            ],
            [
                'table' => 'tl_gutesio_data_child_exhibition',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription'
            ],
            [
                'table' => 'tl_gutesio_data_child',
                'fields' => 'name, description, shortDescription',
                'where' => "typeId IN ('arrangement', 'service')"
            ]
        ];

        // Filter out tables that do not exist
        $db = Database::getInstance();
        return array_filter($config, function($item) use ($db) {
            if (!$db->tableExists($item['table'])) {
                return false;
            }
            if (isset($item['baseTable']) && !$db->tableExists($item['baseTable'])) {
                return false;
            }
            return true;
        });
    }

    public function getResponse(string $userQuestion, $lat = null, $lon = null): array
    {
        $startTotal = microtime(true);
        $settings = GutesioOperatorSettingsModel::findSettings();
        if (!$settings || !$settings->aiEnabled) {
            return ['answer' => "KI Chatbot ist nicht aktiviert."];
        }

        $userCoords = null;
        if ($lat !== null && $lon !== null) {
            $userCoords = [floatval($lon), floatval($lat)]; // AreaService expects [lon, lat]
        }

        // We now use a hybrid approach:
        // 1. A global base context (the latest records) - cached for performance
        // 2. A dynamic search context based on the user question - generated on the fly
        
        $cacheKey = 'ai_context_base_' . ($settings->aiMaxContextRecords ?: 'default');
        $cache = \Contao\System::getContainer()->get('cache.app');
        $cachedBaseData = $cache->getItem($cacheKey);

        if ($cachedBaseData->isHit()) {
            $baseData = $cachedBaseData->get();
            $cacheHit = true;
        } else {
            $baseData = $this->prepareContext($settings, "", 75); // Latest 75 records as base
            $cachedBaseData->set($baseData);
            $cachedBaseData->expiresAfter(3600); // 1 hour cache
            $cache->save($cachedBaseData);
            $cacheHit = false;
        }

        // Merge base and search data, ensuring uniqueness by UUID
        // We increase the search limit to find more relevant records from the large database
        $searchData = $this->prepareContext($settings, $userQuestion, 150); 
        
        $contextData = $this->mergeContextData($baseData, $searchData);

        $context = $contextData['context'];
        $tileData = $contextData['tileData'];
        $distanceData = $contextData['distanceData'] ?? [];

        // Add dynamic distances if coordinates are provided
        if ($userCoords && !empty($distanceData)) {
            $contextLines = explode("\n", trim($context));
            $newContext = "";
            foreach ($contextLines as $line) {
                $uuidMatch = [];
                if (preg_match('/\[([a-f0-9-]+)\]/', $line, $uuidMatch)) {
                    $uuid = $uuidMatch[1];
                    if (isset($distanceData[$uuid])) {
                        $coords = $distanceData[$uuid];
                        $distance = $this->areaService->calculateDistance($userCoords, $coords);
                        if ($distance < 1000) {
                            $line .= ", Entf: " . round($distance) . "m";
                        } else {
                            $line .= ", Entf: " . round($distance / 1000, 1) . "km";
                        }
                    }
                }
                $newContext .= $line . "\n";
            }
            $context = $newContext;
        }

        // Final safety length check
        if (strlen($context) > 45000) {
            $context = mb_substr($context, 0, 44997) . "...";
        }

        $apiStart = microtime(true);
        $assistantName = $settings->aiAssistantName ?: 'KI';
        $additionalKnowledge = $settings->aiAdditionalKnowledge ?: '';
        $systemPrompt = "Du bist eine hilfreiche Assistentin namens " . $assistantName . ". Beantworte Fragen basierend auf den bereitgestellten Daten.
" . ($additionalKnowledge ? "Zusätzliches Projektwissen:\n" . $additionalKnowledge . "\n\n" : "") . "
Nenne wichtige Details wie Adressen, Telefonnummern, Preise oder Öffnungszeiten nur dann, wenn sie für die Beantwortung der Frage wirklich relevant sind. Halte dich bei den Detailbeschreibungen kurz, da der Benutzer weitere Informationen über die bereitgestellten Links findet.
Nutze die Öffnungszeiten (opening_hours), um Fragen zur Verfügbarkeit heute oder an bestimmten Tagen zu beantworten.
Wenn nach Objekten in der Nähe gefragt wird, nutze die Entfernungsangaben.
Wichtig: Wenn du ein Schaufenster oder einen Inhalt (Event, Produkt, Job etc.) empfiehlst oder nennst, füge UNBEDINGT am Ende der jeweiligen Beschreibung den Tag [TILE:UUID] ein (ersetze UUID durch die tatsächliche ID in eckigen Klammern aus dem Kontext, z.B. [TILE:1234-abcd]). Dies ermöglicht es dem System, eine visuelle Kachel anzuzeigen.
Verlinkung:
- Bevorzuge IMMER die internen Links (gekennzeichnet mit 'L:').
- Gib externe Webseiten (gekennzeichnet mit 'website:') NUR aus, wenn der Benutzer explizit danach fragt.
- Gib jeden Link in einer EIGENEN Zeile aus, damit sie sauber getrennt sind.
- Füge KEINE Satzzeichen direkt am Ende einer URL an.

Antworte freundlich, kurz und präzise in der Sprache des Benutzers. Bitte die User duzen.";

        $answer = $this->callAiApi($settings, $systemPrompt, $context, $userQuestion);
        $apiEnd = microtime(true);
        
        // Performance logging
        $totalTime = round((microtime(true) - $startTotal) * 1000, 2);
        $contextTime = round(($contextEnd - $contextStart) * 1000, 2);
        $apiTime = round(($apiEnd - $apiStart) * 1000, 2);
        C4gLogModel::addLogEntry("operator", sprintf(
            "AI Chatbot Performance: Total %sms (Context: %sms, Cache: %s, API: %sms, ContextLength: %d chars)",
            $totalTime, $contextTime, ($cacheHit ? 'HIT' : 'MISS'), $apiTime, strlen($context)
        ));
        
        // Parse the answer for [TILE:UUID] tags and collect data for those tiles
        $foundTiles = [];
        preg_match_all('/\[TILE:([a-f0-9-]+)\]/i', $answer, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $uuid) {
                if (isset($tileData[$uuid])) {
                    $foundTiles[] = $tileData[$uuid];
                }
            }
        }

        return [
            'answer' => $answer,
            'tiles' => $foundTiles
        ];
    }

    private function prepareContext($settings, string $userQuestion = "", int $limit = 0): array
    {
        $context = "";
        $tileData = [];
        $distanceData = [];
        $records = []; // Store raw records to handle uniqueness during merge
        $syncConfig = $this->getTableConfig();
        
        $db = Database::getInstance();
        $maxRecords = $limit;
        if ($maxRecords <= 0) {
            $maxRecords = intval($settings->aiMaxContextRecords);
            if ($maxRecords <= 0) {
                $maxRecords = 150;
            }
        }
        
        $recordsCount = 0;
        $maxContextLength = 30000;

        // Build search condition if user question is provided
        $searchTerms = [];
        if ($userQuestion !== "") {
            $words = preg_split('/[\s,?.!]+/', mb_strtolower($userQuestion), -1, PREG_SPLIT_NO_EMPTY);
            $stopWords = ['der', 'die', 'das', 'und', 'ein', 'eine', 'mit', 'für', 'von', 'aus', 'nach', 'ist', 'sind', 'was', 'wer', 'wie', 'wo', 'gibt', 'mich', 'mir', 'dir', 'dich', 'zeig', 'suche', 'finde', 'hallo', 'moin', 'bitte', 'danke'];
            foreach ($words as $word) {
                if (strlen($word) > 2 && !in_array($word, $stopWords)) {
                    $searchTerms[] = $word;
                }
            }
        }

        // Pre-fetch relations to avoid N+1 queries
        $tableUuids = [];
        
        foreach ($syncConfig as $config) {
            $table = $config['table'];
            $baseTable = $config['baseTable'] ?? null;
            
            $queryTable = $baseTable ?: $table;
            $sql = "SELECT uuid FROM `" . $queryTable . "` WHERE published='1'";
            if (isset($config['where'])) {
                $sql .= " AND (" . $config['where'] . ")";
            }

            if (!empty($searchTerms)) {
                $searchConditions = [];
                foreach ($searchTerms as $term) {
                    $searchConditions[] = "(name LIKE '%" . $term . "%' OR description LIKE '%" . $term . "%' OR shortDescription LIKE '%" . $term . "%' OR fullTextContent LIKE '%" . $term . "%')";
                }
                $sql .= " AND (" . implode(" OR ", $searchConditions) . ")";
            }

            $sql .= " ORDER BY tstamp DESC";
            $sql .= " LIMIT " . ($maxRecords - $recordsCount);
            
            try {
                $objUuids = $db->prepare($sql)->execute();
                while ($objUuids->next()) {
                    $tableUuids[$table][] = $objUuids->uuid;
                }
            } catch (\Throwable $e) {
                // Ignore errors for specific tables
            }
        }
        
        $batchRelations = $this->batchGetRelations($tableUuids);

        foreach ($syncConfig as $config) {
            if ($maxRecords > 0 && $recordsCount >= $maxRecords) {
                break;
            }

            $table = $config['table'];
            if (!$db->tableExists($table)) {
                continue;
            }

            $baseTable = $config['baseTable'] ?? null;
            $fieldsStr = $config['fields'];
            if (!$table || !$fieldsStr) {
                continue;
            }

            $fields = array_map('trim', explode(',', $fieldsStr));
            $selectFields = [];
            foreach ($fields as $field) {
                if (strpos($field, '.') !== false) {
                    $parts = explode('.', $field);
                    $selectFields[] = "`" . $parts[0] . "`.`" . $parts[1] . "`";
                } else {
                    $selectFields[] = "`" . $field . "`";
                }
            }

            if ($baseTable) {
                if (!in_array('`t1`.`id`', $selectFields)) $selectFields[] = '`t1`.`id`';
                if (!in_array('`t1`.`uuid`', $selectFields)) $selectFields[] = '`t1`.`uuid`';
                if (!in_array('`t1`.`alias`', $selectFields)) $selectFields[] = '`t1`.`alias`';
                if (!in_array('`t1`.`published`', $selectFields)) $selectFields[] = '`t1`.`published`';
                if ($baseTable === 'tl_gutesio_data_child' && !in_array('`t1`.`typeId`', $selectFields)) {
                    $selectFields[] = '`t1`.`typeId`';
                }
                if (!in_array('`t1`.`imageCDN`', $selectFields)) $selectFields[] = '`t1`.`imageCDN`';
                $query = "SELECT " . implode(',', $selectFields) . " FROM `" . $table . "` t2 INNER JOIN `" . $baseTable . "` t1 ON t2.`childId` = t1.`uuid` WHERE t1.`published`='1'";
            } else {
                if (!in_array('`id`', $selectFields)) $selectFields[] = '`id`';
                if (!in_array('`uuid`', $selectFields)) $selectFields[] = '`uuid`';
                if (!in_array('`alias`', $selectFields)) $selectFields[] = '`alias`';
                if (!in_array('`published`', $selectFields)) $selectFields[] = '`published`';
                if ($table === 'tl_gutesio_data_child' && !in_array('`typeId`', $selectFields)) {
                    $selectFields[] = '`typeId`';
                }
                if (!in_array('`imageCDN`', $selectFields)) $selectFields[] = '`imageCDN`';
                if ($table === 'tl_gutesio_data_element') {
                    if (!in_array('`geox`', $selectFields)) $selectFields[] = '`geox`';
                    if (!in_array('`geoy`', $selectFields)) $selectFields[] = '`geoy`';
                }
                $query = "SELECT " . implode(',', $selectFields) . " FROM `" . $table . "` WHERE `published`='1'";
            }

            if (isset($config['where'])) {
                $query .= " AND (" . $config['where'] . ")";
            }

            if (!empty($searchTerms)) {
                $queryTableAlias = $baseTable ? 't1' : '`'.$table.'`';
                $searchConditions = [];
                foreach ($searchTerms as $term) {
                    $searchConditions[] = "(" . $queryTableAlias . ".name LIKE '%" . $term . "%' OR " . $queryTableAlias . ".description LIKE '%" . $term . "%' OR " . $queryTableAlias . ".shortDescription LIKE '%" . $term . "%' OR " . $queryTableAlias . ".fullTextContent LIKE '%" . $term . "%')";
                }
                $query .= " AND (" . implode(" OR ", $searchConditions) . ")";
            }

            $queryTableAlias = $baseTable ? 't1' : '`'.$table.'`';
            $query .= " ORDER BY " . $queryTableAlias . ".`tstamp` DESC";

            if ($maxRecords > 0) {
                $query .= " LIMIT " . ($maxRecords - $recordsCount);
            }
            
            try {
                $objRows = $db->prepare($query)->execute();
                while ($objRows->next()) {
                    $rowContext = [];
                    $rowContext[] = "[" . $objRows->uuid . "]";
                    foreach ($fields as $field) {
                        $columnName = $field;
                        if (strpos($field, '.') !== false) {
                            $parts = explode('.', $field);
                            $columnName = end($parts);
                        }
                        $val = $objRows->$columnName;
                        if (in_array($columnName, ['beginDate', 'endDate', 'dateOfBirth']) && $val && is_numeric($val)) {
                            $val = date('d.m.Y', (int)$val);
                        } elseif (in_array($columnName, ['beginTime', 'endTime', 'startTime']) && $val && is_numeric($val)) {
                            $val = date('H:i', (int)$val);
                        }
                        if ($columnName === 'opening_hours' && $val) {
                            $val = str_replace("\\", "", (string)$val);
                        }
                        if (in_array($columnName, ['description', 'shortDescription']) && strlen((string)$val) > 250) {
                            $val = mb_substr((string)$val, 0, 247) . "...";
                        }
                        if ($val !== null && $val !== '') {
                            $rowContext[] = $columnName . ": " . $val;
                        }
                    }

                    $relations = $batchRelations[$objRows->uuid] ?? ['categories' => '', 'tags' => ''];
                    if ($relations['categories']) {
                        $rowContext[] = "Kat: " . $relations['categories'];
                    }
                    if ($relations['tags']) {
                        $rowContext[] = "Tags: " . $relations['tags'];
                    }

                    if ($table === 'tl_gutesio_data_element' && $objRows->geox && $objRows->geoy) {
                        $distanceData[$objRows->uuid] = [$objRows->geox, $objRows->geoy];
                    }
                    
                    $link = $this->generateLink($table, $objRows, $settings);
                    if ($link) {
                        $rowContext[] = "L: " . $link;
                    }
                    
                    $rowStr = implode(", ", $rowContext);
                    $records[$objRows->uuid] = [
                        'context' => $rowStr,
                        'tile' => [
                            'uuid' => $objRows->uuid,
                            'name' => $objRows->name,
                            'image' => $this->formatImageUrl($objRows->imageCDN, $settings),
                            'link' => $link,
                            'type' => ($table === 'tl_gutesio_data_element' ? 'element' : 'child'),
                            'typeName' => $relations['categories']
                        ],
                        'distance' => ($distanceData[$objRows->uuid] ?? null)
                    ];

                    $recordsCount++;
                }
            } catch (\Throwable $e) {
                // Ignore errors for specific tables
            }
        }

        return ['records' => $records];
    }

    private function mergeContextData(array $baseData, array $searchData): array
    {
        // Merge records, search results take precedence or just fill up
        $allRecords = $searchData['records'] + $baseData['records'];
        
        $context = "";
        $tileData = [];
        $distanceData = [];
        
        foreach ($allRecords as $uuid => $data) {
            $context .= $data['context'] . "\n";
            $tileData[$uuid] = $data['tile'];
            if ($data['distance']) {
                $distanceData[$uuid] = $data['distance'];
            }
        }
        
        return [
            'context' => $context,
            'tileData' => $tileData,
            'distanceData' => $distanceData
        ];
    }

    private function formatImageUrl($image, $settings): string
    {
        if ($image && strpos($image, 'http') !== 0 && strpos($image, '/') !== 0) {
            $cdnUrl = $settings->cdnUrl;
            if ($cdnUrl) {
                $image = rtrim($cdnUrl, '/') . '/' . ltrim($image, '/');
            }
        }
        return (string)$image;
    }

    private function batchGetRelations(array $tableUuids): array
    {
        $db = Database::getInstance();
        $relations = [];

        foreach ($tableUuids as $table => $uuids) {
            if (empty($uuids)) continue;
            
            if ($table === 'tl_gutesio_data_element') {
                // Element Categories
                $sql = "SELECT t1.elementId, t2.name FROM tl_gutesio_data_element_type t1 JOIN tl_gutesio_data_type t2 ON t1.typeId = t2.uuid WHERE t1.elementId IN ('" . implode("','", $uuids) . "')";
                $objResult = $db->prepare($sql)->execute();
                while ($objResult->next()) {
                    $relations[$objResult->elementId]['categories'][] = $objResult->name;
                }

                // Element Tags
                $sql = "SELECT t1.elementId, t2.name FROM tl_gutesio_data_tag_element t1 JOIN tl_gutesio_data_tag t2 ON t1.tagId = t2.uuid WHERE t1.elementId IN ('" . implode("','", $uuids) . "')";
                $objResult = $db->prepare($sql)->execute();
                while ($objResult->next()) {
                    $relations[$objResult->elementId]['tags'][] = $objResult->name;
                }
            } elseif (strpos($table, 'tl_gutesio_data_child') === 0) {
                // Child Categories
                $sql = "SELECT t1.uuid, t2.name FROM tl_gutesio_data_child t1 JOIN tl_gutesio_data_child_type t2 ON t1.typeId = t2.uuid WHERE t1.uuid IN ('" . implode("','", $uuids) . "')";
                $objResult = $db->prepare($sql)->execute();
                while ($objResult->next()) {
                    $relations[$objResult->uuid]['categories'][] = $objResult->name;
                }

                // Child Tags
                $sql = "SELECT t1.childId, t2.name FROM tl_gutesio_data_child_tag t1 JOIN tl_gutesio_data_tag t2 ON t1.tagId = t2.uuid WHERE t1.childId IN ('" . implode("','", $uuids) . "')";
                $objResult = $db->prepare($sql)->execute();
                while ($objResult->next()) {
                    $relations[$objResult->childId]['tags'][] = $objResult->name;
                }
            }
        }

        // Stringify categories and tags
        foreach ($relations as $uuid => $data) {
            $relations[$uuid]['categories'] = isset($data['categories']) ? implode(', ', $data['categories']) : '';
            $relations[$uuid]['tags'] = isset($data['tags']) ? implode(', ', $data['tags']) : '';
        }

        return $relations;
    }

    private function generateLink(string $table, $row, $settings): string
    {
        $pageId = 0;
        $params = '/' . ($row->alias ?: $row->id);

        if ($table === 'tl_gutesio_data_element') {
            $pageId = $settings->showcaseDetailPage;
        } elseif (strpos($table, 'tl_gutesio_data_child') === 0) {
            $typeId = $row->typeId;
            switch ($typeId) {
                case 'event':
                    $pageId = $settings->eventDetailPage;
                    break;
                case 'product':
                    $pageId = $settings->productDetailPage;
                    break;
                case 'job':
                    $pageId = $settings->jobDetailPage;
                    break;
                case 'voucher':
                    $pageId = $settings->voucherDetailPage;
                    break;
                case 'person':
                    $pageId = $settings->personDetailPage;
                    break;
                case 'arrangement':
                    $pageId = $settings->arrangementDetailPage;
                    break;
                case 'service':
                    $pageId = $settings->serviceDetailPage;
                    break;
                case 'realestate':
                    $pageId = $settings->realestateDetailPage;
                    break;
                case 'exhibition':
                    $pageId = $settings->exhibitionDetailPage;
                    break;
            }
        }

        if ($pageId && is_numeric($pageId)) {
            try {
                $page = PageModel::findByPk($pageId);
                if ($page instanceof PageModel) {
                    return $page->getAbsoluteUrl($params);
                }
            } catch (\Throwable $e) {
                // Ignore errors during link generation
            }
        }

        return "";
    }

    private function getRelations(string $table, string $uuid): array
    {
        $db = Database::getInstance();
        $categories = [];
        $tags = [];

        if ($table === 'tl_gutesio_data_element') {
            // Element Categories
            $sql = "SELECT t2.name FROM tl_gutesio_data_element_type t1 JOIN tl_gutesio_data_type t2 ON t1.typeId = t2.uuid WHERE t1.elementId = ?";
            $objResult = $db->prepare($sql)->execute($uuid);
            while ($objResult->next()) {
                $categories[] = $objResult->name;
            }

            // Element Tags
            $sql = "SELECT t2.name FROM tl_gutesio_data_tag_element t1 JOIN tl_gutesio_data_tag t2 ON t1.tagId = t2.uuid WHERE t1.elementId = ?";
            $objResult = $db->prepare($sql)->execute($uuid);
            while ($objResult->next()) {
                $tags[] = $objResult->name;
            }
        } elseif (strpos($table, 'tl_gutesio_data_child') === 0) {
            // Child Categories (base table has typeId)
            $sql = "SELECT t2.name FROM tl_gutesio_data_child t1 JOIN tl_gutesio_data_child_type t2 ON t1.typeId = t2.uuid WHERE t1.uuid = ?";
            $objResult = $db->prepare($sql)->execute($uuid);
            while ($objResult->next()) {
                $categories[] = $objResult->name;
            }

            // Child Tags
            $sql = "SELECT t2.name FROM tl_gutesio_data_child_tag t1 JOIN tl_gutesio_data_tag t2 ON t1.tagId = t2.uuid WHERE t1.childId = ?";
            $objResult = $db->prepare($sql)->execute($uuid);
            while ($objResult->next()) {
                $tags[] = $objResult->name;
            }
        }

        return [
            'categories' => implode(', ', $categories),
            'tags' => implode(', ', $tags)
        ];
    }

    private function callAiApi($settings, $systemPrompt, $context, $userQuestion): string
    {
        $endpoint = trim($settings->aiApiEndpoint);
        $apiKey = trim($settings->aiApiKey);
        $model = trim($settings->aiModel ?: 'gpt-4o');

        if (!$endpoint || !$apiKey) {
            return "KI Konfiguration unvollständig (Endpunkt oder API Key fehlt).";
        }

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return "Fehler: Der KI API Endpunkt '" . $endpoint . "' ist keine gültige URL. Bitte geben Sie eine vollständige URL an (z.B. https://api.openai.com/v1/chat/completions).";
        }

        $postRequest = new CurlPostRequest();
        $postRequest->setUrl($endpoint);
        $postRequest->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey
        ]);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt . "\n\nKontext:\n" . $context],
            ['role' => 'user', 'content' => $userQuestion]
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages
        ];

        // Only set temperature if it's not a model known to not support it
        // OpenAI o1, o3 and future 'reasoning' models often don't support custom temperatures
        // We also check for 'gpt-5' just in case (as seen in some test environments)
        $isReasoningModel = (
            strpos($model, 'o1') !== false || 
            strpos($model, 'o3') !== false || 
            strpos($model, 'reasoning') !== false || 
            strpos($model, 'gpt-5') !== false
        );
        
        if (!$isReasoningModel) {
            $payload['temperature'] = 0.5;
        }

        $postRequest->setPostData(json_encode($payload));

        try {
            $response = $postRequest->send();
            $responseData = $response->getData();
            $statusCode = $response->getStatusCode();
            
            // If we get a 400 error specifically about 'temperature', retry without it
            if ($statusCode === 400 && strpos($responseData, 'temperature') !== false && isset($payload['temperature'])) {
                unset($payload['temperature']);
                $postRequest->setPostData(json_encode($payload));
                $response = $postRequest->send();
                $responseData = $response->getData();
                $statusCode = $response->getStatusCode();
            }
            
            if ($statusCode === 200 || $statusCode === "200") {
                if (!$responseData) {
                    C4gLogModel::addLogEntry("operator", "AI API Empty Response. Status: " . $statusCode);
                    return "Fehler: Die KI hat eine leere Antwort geliefert.";
                }
                
                $data = json_decode($responseData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    C4gLogModel::addLogEntry("operator", "AI API JSON Decode Error: " . json_last_error_msg() . " - Raw Data: " . $responseData);
                    return "Fehler: Die Antwort der KI konnte nicht verarbeitet werden (JSON Fehler).";
                }
                
                if (isset($data['choices'][0]['message']['content'])) {
                    return $data['choices'][0]['message']['content'];
                }
                
                C4gLogModel::addLogEntry("operator", "AI API Invalid Response Structure: " . $responseData);
                return "Fehler: Die KI hat eine ungültige Antwortstruktur geliefert.";
            } else {
                $errorMsg = "Fehler bei der Anfrage an die KI (HTTP " . $statusCode . ")";
                
                // Try to parse error message from API response
                if ($responseData) {
                    $errorData = json_decode($responseData, true);
                    if (isset($errorData['error']['message'])) {
                        $errorMsg .= ": " . $errorData['error']['message'];
                    } elseif ($statusCode === 0) {
                        $errorMsg .= ": " . $responseData;
                    }
                }
                
                $hint = "";
                if ($statusCode === 404) {
                    if (strpos($endpoint, 'openai.com') !== false && strpos($endpoint, '/chat/completions') === false) {
                        $hint = " Hinweis: Für OpenAI muss der Endpunkt in der Regel auf '/v1/chat/completions' enden.";
                    }
                    if (strpos($model, '40') !== false) {
                        $hint .= " Hinweis: Prüfen Sie den Modellnamen (Tippfehler '40' statt '4o'?).";
                    }
                }
                
                C4gLogModel::addLogEntry("operator", "AI API Error: HTTP " . $statusCode . " - URL: " . $endpoint . " - Content: " . $responseData);
                return $errorMsg . "." . $hint . " Bitte prüfen Sie das System-Log.";
            }
        } catch (\Exception $e) {
            C4gLogModel::addLogEntry("operator", "AI API Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return "Ausnahme bei der Anfrage an die KI: " . $e->getMessage();
        }
    }
}
