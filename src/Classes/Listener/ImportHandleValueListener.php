<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\Events\AfterImportEvent;
use con4gis\CoreBundle\Classes\Events\ImportHandleDatabaseValueEvent;
use Contao\Database;
use Contao\StringUtil;

class ImportHandleValueListener
{
    private $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function onHandleDatabaseValue(ImportHandleDatabaseValueEvent $event): void
    {
        // Handled via onImportSkip to bypass fragile core SQL generation
    }

    /**
     * Manually handle import for tl_c4g_editor_configuration to bypass core issues with JSON data.
     */
    public function onImportSkip(\con4gis\CoreBundle\Classes\Events\ImportSkipDatasetEvent $event): void
    {
        $tableName = $event->getTableName();
        if ($tableName !== 'tl_c4g_editor_configuration') {
            return;
        }

        $dataset = $event->getDataset();
        $db = Database::getInstance();

        try {
            // Find active import for metadata (UUID and path)
            $import = $db->prepare("SELECT id, importUuid, importFilePath FROM tl_c4g_import_data WHERE importRunning = '1' ORDER BY tstamp DESC")
                ->execute()->fetchAssoc();

            if (!$import) {
                // Fallback for recently finished imports
                $import = $db->prepare("SELECT id, importUuid, importFilePath FROM tl_c4g_import_data WHERE tstamp > ? ORDER BY tstamp DESC")
                    ->execute(time() - 1800)->fetchAssoc();
            }

            if (!$import) {
                return;
            }

            $importRecordId = $import['id'];
            $importUuid = $import['importUuid'];
            $importFilePath = $import['importFilePath'];

            // Map IDs within the dataset before insertion
            $map = $this->getLocstyleMap($importRecordId, $importFilePath);
            if ($map) {
                foreach (['types', 'editor_vars'] as $field) {
                    if (!empty($dataset[$field])) {
                        $data = json_decode($dataset[$field], true);
                        if (!is_array($data)) {
                            $data = StringUtil::deserialize($dataset[$field]);
                        }
                        if (is_array($data)) {
                            $this->mapRecursive($data, $map);
                            $dataset[$field] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    }
                }
            }

            // Prepare for insertion/update
            $dataset['importId'] = $importUuid;
            $dataset['tstamp'] = time();

            // Check if record already exists (using Server ID as mapping)
            // Note: Since we don't have UUIDs, we have to rely on the ID mapping or name.
            // But usually, during a full import, the table is cleared or we use the mapping.
            // For simplicity, we try to find the local ID via id-config.json.
            $localId = null;
            $idConfigPath = str_replace('//', '/', $this->projectDir . '/files/con4gis_import_data/' . $importRecordId . '/id-config.json');
            if (file_exists($idConfigPath)) {
                $idConfig = json_decode(file_get_contents($idConfigPath), true);
                $localId = $idConfig['tl_c4g_editor_configuration']['id'][$dataset['id']] ?? null;
            }

            $fields = ['importId', 'name', 'types', 'editor_vars', 'editor_helpurl', 'editor_project_group', 'tstamp'];
            $set = [];
            $vals = [];
            foreach ($fields as $field) {
                $set[] = "`$field` = ?";
                $vals[] = $dataset[$field] ?? '';
            }

            if ($localId) {
                $vals[] = $localId;
                $db->prepare("UPDATE tl_c4g_editor_configuration SET " . implode(', ', $set) . " WHERE id = ?")
                    ->execute(...$vals);
            } else {
                $db->prepare("INSERT INTO tl_c4g_editor_configuration SET " . implode(', ', $set))
                    ->execute(...$vals);
            }

            $event->setSkip(true);

        } catch (\Throwable $e) {
            error_log("ImportHandleValueListener onImportSkip Error: " . $e->getMessage());
        }
    }

    /**
     * Map IDs after import is finished.
     */
    public function onAfterImport(AfterImportEvent $event): void
    {
        try {
            $db = Database::getInstance();
            
            // Find active or recent import
            $import = $db->prepare("SELECT id, importUuid, importFilePath FROM tl_c4g_import_data WHERE importRunning = '1' ORDER BY tstamp DESC")
                ->execute()->fetchAssoc();

            if (!$import) {
                // Fallback: take latest import from last 30 minutes
                $import = $db->prepare("SELECT id, importUuid, importFilePath FROM tl_c4g_import_data WHERE tstamp > ? ORDER BY tstamp DESC")
                    ->execute(time() - 1800)->fetchAssoc();
            }

            if (!$import) {
                return;
            }

            $importRecordId = $import['id'];
            $importUuid = $import['importUuid'];
            $importFilePath = $import['importFilePath'];
            
            $map = $this->getLocstyleMap($importRecordId, $importFilePath);
            if (!$map) {
                return;
            }

            $configs = $db->prepare("SELECT id, types, editor_vars FROM tl_c4g_editor_configuration WHERE importId = ?")
                ->execute($importUuid)->fetchAllAssoc();

            foreach ($configs as $config) {
                $updatedFields = [];
                foreach (['types', 'editor_vars'] as $field) {
                    if (!empty($config[$field])) {
                        $data = json_decode($config[$field], true);
                        if (!is_array($data)) {
                            $data = StringUtil::deserialize($config[$field]);
                        }

                        if (is_array($data)) {
                            if ($this->mapRecursive($data, $map)) {
                                $updatedFields[$field] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        }
                    }
                }

                if (!empty($updatedFields)) {
                    $set = [];
                    $vals = [];
                    foreach ($updatedFields as $f => $v) {
                        $set[] = "`$f` = ?";
                        $vals[] = $v;
                    }
                    $vals[] = $config['id'];
                    $db->prepare("UPDATE tl_c4g_editor_configuration SET " . implode(', ', $set) . " WHERE id = ?")
                        ->execute(...$vals);
                }
            }
        } catch (\Throwable $e) {
            error_log("ImportHandleValueListener onAfterImport Error: " . $e->getMessage());
        }
    }

    private function mapRecursive(array &$data, array $map): bool
    {
        $changed = false;
        foreach ($data as $key => &$value) {
            if ($key === 'locstyle' && (is_string($value) || is_int($value))) {
                if (isset($map[$value])) {
                    $value = $map[$value];
                    $changed = true;
                }
            } elseif (is_array($value)) {
                if ($this->mapRecursive($value, $map)) {
                    $changed = true;
                }
            }
        }
        return $changed;
    }

    private function getLocstyleMap($importId, $path): array
    {
        if (!$path) {
            $import = Database::getInstance()->prepare("SELECT importFilePath FROM tl_c4g_import_data WHERE id = ?")
                ->execute($importId)->fetchAssoc();
            $path = $import['importFilePath'] ?? '';
        }

        if (!$path) {
            return [];
        }

        $pathsToTry = [
            $this->projectDir . '/files' . $path . '/id-config.json',
            $this->projectDir . $path . '/id-config.json',
            $this->projectDir . '/files/con4gis_import_data/' . $importId . '/id-config.json'
        ];

        foreach ($pathsToTry as $idConfigPath) {
            $idConfigPath = str_replace('//', '/', $idConfigPath);
            if (file_exists($idConfigPath)) {
                $idConfig = json_decode(file_get_contents($idConfigPath), true);
                return $idConfig['tl_c4g_map_locstyles']['id'] ?? [];
            }
        }

        return [];
    }
}
