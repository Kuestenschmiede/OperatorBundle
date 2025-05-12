<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Cron;

use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\System;
use con4gis\CoreBundle\Classes\Callback\C4GImportDataCallback;
use gutesio\OperatorBundle\Classes\Cache\OfferDataCache;

class SyncDataCron
{


    public function __construct(
        private ContaoFramework $framework
    ) {
    }

    public function onMinutely()
    {
        $this->framework->initialize();
        $db = Database::getInstance();


        $c4gSettings = $db->prepare('SELECT * FROM tl_c4g_settings')->execute()->fetchAssoc();
        if (!key_exists('disableImports',$c4gSettings)  && $c4gSettings['syncDataAutomaticly'] == 1) {
            $importData = $db->prepare('SELECT * FROM tl_c4g_import_data WHERE importRunning=1')->execute()->fetchAllAssoc();
            foreach ($importData as $import) {
                $db->prepare("UPDATE tl_c4g_import_data SET tstamp=?, importRunning='' WHERE tstamp<=? AND importRunning='1' AND id=?")
                    ->execute(time(), time() - 600, $import['id']);
            }
            $importData = $db->prepare('SELECT * FROM tl_c4g_import_data WHERE importRunning=1')->execute()->fetchAllAssoc();

            if (!$importData) {
                $importDataClass = new C4GImportDataCallback();
                $importIds = $importDataClass->loadBaseData(true);
                if (!empty($importIds)) {
                    foreach ($importIds as $importId) {
                        $currentImport = $db->prepare('SELECT availableVersion, importVersion FROM tl_c4g_import_data WHERE id=? AND type=?')->execute($importId, 'gutesio')->fetchAssoc();
                        if ($currentImport['availableVersion'] && $currentImport['importVersion'] && ($currentImport['availableVersion'] > $currentImport['importVersion'])) {
                            $importDataClass->updateBaseData($importId, true);
                            // clear offer data cache after importing
                            OfferDataCache::getInstance()->clearCache();
                        }
                    }
                }
            }/* else {
                C4gLogModel::addLogEntry('core', 'Cant update available import data. Import currently running.');
            }*/
        }
    }
}
