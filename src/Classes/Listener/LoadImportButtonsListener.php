<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\Events\BeforeImportButtonLoadEvent;
use Contao\Database;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LoadImportButtonsListener
{
    public function beforeLoadImportButtons(BeforeImportButtonLoadEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        $database = Database::getInstance();
        $vendor = 'gutesio';
        $importType = ['gutesio', 'demo'];
        $releaseCompatible = false;
        $importCompatible = true;
        $import = $event->getImportData();

        $c4gSettings = $database->prepare('SELECT * FROM tl_c4g_settings')->execute()->fetchAssoc();
        if ($c4gSettings['syncDataAutomaticly'] == '') {
            $updateCompatible = true;
        } else {
            $updateCompatible = false;
        }

        //hotfix
        $dataset = $database->prepare("SELECT * FROM tl_c4g_import_data WHERE source='gutesio'")
            ->execute()->fetchAllAssoc();
        if ($dataset && count($dataset) > 1) {
            $dataset = $database->prepare("DELETE FROM tl_c4g_import_data WHERE source='gutesio'")
                ->execute();
            return;
        } else {
            $betreiberdatenImport = $database->prepare("SELECT * FROM tl_c4g_import_data WHERE source='gutesio' && type=? && importVersion!=''")
                ->execute($import['type'])->fetchAssoc();
            if ($betreiberdatenImport) {
                $importCompatible = false;
            }

            $event->setVendor($vendor);
            $event->setImportCompatible($importCompatible);
            $event->setReleaseCompatible($releaseCompatible);
            $event->setUpdateCompatible($updateCompatible);
            $event->setImportType($importType);
        }
    }
}
