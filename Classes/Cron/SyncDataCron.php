<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes\Cron;

use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use Contao\Database;
use Contao\System;
use con4gis\CoreBundle\Classes\Callback\C4GImportDataCallback;

class SyncDataCron
{
    public function onMinutely()
    {
        $db = Database::getInstance();
        $em = System::getContainer()->get('doctrine.orm.default_entity_manager');

        $c4gSettings = $memberData = $db->prepare('SELECT * FROM tl_c4g_settings')->execute()->fetchAssoc();
        if ($c4gSettings['syncDataAutomaticly'] == 1) {
            $importData = $db->prepare('SELECT * FROM tl_c4g_import_data WHERE importRunning=1')->execute()->fetchAllAssoc();
            foreach ($importData as $import) {
                $this->Database->prepare("UPDATE tl_c4g_import_data SET tstamp=?, importRunning='' WHERE tstamp<=? AND importRunning='1' AND id=?")
                    ->execute(time(), time()-600, $import['id']);
            }
            $importData = $db->prepare('SELECT * FROM tl_c4g_import_data WHERE importRunning=1')->execute()->fetchAllAssoc();

            if (!$importData) {
                $importDataClass = new C4GImportDataCallback();
                $importIds = $importDataClass->loadBaseData(true);
                if (!empty($importIds)) {
                    foreach ($importIds as $importId) {
                        $currentImport = $db->prepare('SELECT importVersion FROM tl_c4g_import_data WHERE id=? AND type=?')->execute($importId, 'gutesio')->fetchAssoc();
                        if ($currentImport['importVersion'] != '') {
                            $importDataClass->updateBaseData($importId);
                        }
                    }
                }
            } else {
                C4gLogModel::addLogEntry('core', 'Cant update available import data. Import currently running.');
            }
        }
    }
}
