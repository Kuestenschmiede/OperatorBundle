<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\Events\AfterImportEvent;
use con4gis\CoreBundle\Classes\Events\ImportHandleDatabaseValueEvent;
use con4gis\CoreBundle\Classes\Events\ImportHandleSerializedValueEvent;
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
        $tableName = $event->getTableName();
        $fieldName = $event->getFieldName();
        $value = $event->getValue();

        if ($tableName === 'tl_c4g_editor_configuration' && ($fieldName === 'types' || $fieldName === 'editor_vars')) {
            if ($value === 0 || $value === "0" || $value === "") {
                $event->setValue('[]');
                return;
            }

            if (is_string($value)) {
                $cleanValue = str_replace('\"', '"', $value);
                if (strpos($cleanValue, 'a:') === 0 || strpos($cleanValue, 's:') === 0 || strpos($cleanValue, 'O:') === 0) {
                    $unserialized = StringUtil::deserialize($cleanValue);
                    if ($unserialized !== $cleanValue) {
                        $value = json_encode($unserialized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $event->setValue($value);
                    }
                }
            }

            if ($fieldName === 'types') {
                $this->mapLocstyleIds($event);
            }
        }
    }

    public function onHandleSerializedValue(ImportHandleSerializedValueEvent $event): void
    {
        $tableName = $event->getTableName();
        $fieldName = $event->getFieldName();
        $unserializedValue = $event->getUnserializedValue();

        if ($tableName === 'tl_c4g_editor_configuration' && ($fieldName === 'types' || $fieldName === 'editor_vars')) {
            if ($fieldName === 'types' && is_array($unserializedValue)) {
                $map = $this->getLocstyleMap();
                if ($map) {
                    $changed = false;
                    foreach ($unserializedValue as &$type) {
                        if (isset($type['locstyle']) && isset($map[$type['locstyle']])) {
                            $type['locstyle'] = (string)$map[$type['locstyle']];
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        $event->setValue(json_encode($unserializedValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        return;
                    }
                }
            }
            $event->setValue(json_encode($unserializedValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    public function onAfterImport(AfterImportEvent $event): void
    {
        $db = Database::getInstance();
        $import = $db->prepare("SELECT id FROM tl_c4g_import_data WHERE importRunning = '1'")
            ->execute()->fetchAssoc();

        if (!$import) {
            return;
        }

        $importId = $import['id'];
        $map = $this->getLocstyleMap($importId);
        if (!$map) {
            return;
        }

        $configs = $db->prepare("SELECT id, types FROM tl_c4g_editor_configuration WHERE importId = ?")
            ->execute($importId)->fetchAllAssoc();

        foreach ($configs as $config) {
            $types = json_decode($config['types'], true);
            if (is_array($types)) {
                $changed = false;
                foreach ($types as &$type) {
                    if (isset($type['locstyle']) && isset($map[$type['locstyle']])) {
                        $type['locstyle'] = (string)$map[$type['locstyle']];
                        $changed = true;
                    }
                }

                if ($changed) {
                    $db->prepare("UPDATE tl_c4g_editor_configuration SET types = ? WHERE id = ?")
                        ->execute(json_encode($types, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $config['id']);
                }
            }
        }
    }

    private function mapLocstyleIds($event): void
    {
        $value = $event->getValue();
        if (!$value || !is_string($value)) {
            return;
        }

        $types = json_decode($value, true);
        if (!is_array($types)) {
            return;
        }

        $map = $this->getLocstyleMap();
        if (!$map) {
            return;
        }

        $changed = false;
        foreach ($types as &$type) {
            if (isset($type['locstyle']) && isset($map[$type['locstyle']])) {
                $type['locstyle'] = (string)$map[$type['locstyle']];
                $changed = true;
            }
        }

        if ($changed) {
            $event->setValue(json_encode($types, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    private function getLocstyleMap($importId = null): array
    {
        $db = Database::getInstance();
        if ($importId === null) {
            $import = $db->prepare("SELECT id, importFilePath FROM tl_c4g_import_data WHERE importRunning = '1'")
                ->execute()->fetchAssoc();
            if (!$import) {
                return [];
            }
            $importId = $import['id'];
            $path = $import['importFilePath'];
        } else {
            $import = $db->prepare("SELECT importFilePath FROM tl_c4g_import_data WHERE id = ?")
                ->execute($importId)->fetchAssoc();
            $path = $import['importFilePath'] ?? '';
        }

        if (!$path) {
            $path = '/con4gis_import/' . $importId;
        }

        $idConfigPath = $this->projectDir . '/files' . $path . '/id-config.json';
        if (!file_exists($idConfigPath)) {
            $idConfigPath = $this->projectDir . '/var/cache/con4gis_import/' . $importId . '/id-config.json';
        }

        if (!file_exists($idConfigPath)) {
            return [];
        }

        $idConfig = json_decode(file_get_contents($idConfigPath), true);
        return $idConfig['tl_c4g_map_locstyles']['id'] ?? [];
    }
}
