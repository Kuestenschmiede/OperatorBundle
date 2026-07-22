<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\Events\ImportHandleDatabaseValueEvent;
use con4gis\CoreBundle\Classes\Events\ImportHandleSerializedValueEvent;

class ImportHandleValueListener
{
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
                    $unserialized = \Contao\StringUtil::deserialize($cleanValue);
                    if ($unserialized !== $cleanValue) {
                        $event->setValue(json_encode($unserialized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    }
                }
            }
        }
    }

    public function onHandleSerializedValue(ImportHandleSerializedValueEvent $event): void
    {
        $tableName = $event->getTableName();
        $fieldName = $event->getFieldName();
        $unserializedValue = $event->getUnserializedValue();

        if ($tableName === 'tl_c4g_editor_configuration' && ($fieldName === 'types' || $fieldName === 'editor_vars')) {
            $event->setValue(json_encode($unserializedValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
}
