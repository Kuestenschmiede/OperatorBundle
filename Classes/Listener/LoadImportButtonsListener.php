<?php
/**
 * This file is part of con4gis,
 * the gis-kit for Contao CMS.
 *
 * @package   	con4gis
 * @version    7
 * @author  	    con4gis contributors (see "authors.txt")
 * @license 	    LGPL-3.0-or-later
 * @copyright 	Küstenschmiede GmbH Software & Design
 * @link              https://www.con4gis.org
 */
namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\Events\BeforeImportButtonLoadEvent;
use Contao\Database;
use gutesio\DataModelBundle\Classes\ChildFullTextContentUpdater;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LoadImportButtonsListener
{
    public function beforeLoadImportButtons(BeforeImportButtonLoadEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {

        $database = Database::getInstance();
        $vendor = "gutesio";
        $releaseCompatible = false;
        $importCompatible = true;

        $c4gSettings = $database->prepare("SELECT * FROM tl_c4g_settings")->execute()->fetchAssoc();
        if ($c4gSettings['syncDataAutomaticly'] == "") {
            $updateCompatible = true;
        } else {
            $updateCompatible = false;
        }

        $betreiberdatenImport = $database->prepare("SELECT * FROM tl_c4g_import_data WHERE source='gutesio' && type='gutesio' && importVersion!=''")
            ->execute()->fetchAssoc();
        if ($betreiberdatenImport) {
            $importCompatible = false;
        }

        $event->setVendor($vendor);
        $event->setImportCompatible($importCompatible);
        $event->setReleaseCompatible($releaseCompatible);
        $event->setUpdateCompatible($updateCompatible);

    }
}