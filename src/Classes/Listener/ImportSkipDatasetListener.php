<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\Events\ImportSkipDatasetEvent;
use Contao\Database;

class ImportSkipDatasetListener
{
    public function onImportSkip(ImportSkipDatasetEvent $event)
    {
        $importDB = $event->getTableName();
        $importDataset = $event->getDataset();
        // check for already existing relations
        $db = Database::getInstance();
        switch ($importDB) {
            case "tl_gutesio_data_tag_element":
            case "tl_gutesio_data_tag_element_values":
                $query = "SELECT * FROM $importDB WHERE `elementId` = ? and `tagId` = ?";
                $result = $db->prepare($query)->execute($importDataset['elementId'], $importDataset['tagId'])->fetchAllAssoc();
                if (count($result) > 0) {
                    // skip dataset
                    $event->setSkip(true);
                }
                break;
            case "tl_gutesio_data_child_tag":
            case "tl_gutesio_data_child_tag_values":
                $query = "SELECT * FROM $importDB WHERE `childId` = ? and `tagId` = ?";
                $result = $db->prepare($query)->execute($importDataset['childId'], $importDataset['tagId'])->fetchAllAssoc();
                if (count($result) > 0) {
                    // skip dataset
                    $event->setSkip(true);
                }
                break;
            case "tl_gutesio_data_tag_type":
                $query = "SELECT * FROM $importDB WHERE `typeId` = ? and `tagId` = ?";
                $result = $db->prepare($query)->execute($importDataset['typeId'], $importDataset['tagId'])->fetchAllAssoc();
                if (count($result) > 0) {
                    // skip dataset
                    $event->setSkip(true);
                }
                break;
            case "tl_gutesio_data_directory_type":
                $query = "SELECT * FROM $importDB WHERE `typeId` = ? and `directoryId` = ?";
                $result = $db->prepare($query)->execute($importDataset['typeId'], $importDataset['directoryId'])->fetchAllAssoc();
                if (count($result) > 0) {
                    // skip dataset
                    $event->setSkip(true);
                }
                break;
            case "tl_gutesio_data_element_type":
                $query = "SELECT * FROM $importDB WHERE `elementId` = ? and `typeId` = ?";
                $result = $db->prepare($query)->execute($importDataset['elementId'], $importDataset['typeId'])->fetchAllAssoc();
                if (count($result) > 0) {
                    // skip dataset
                    $event->setSkip(true);
                }
                break;
            default:
                break;
        }
    }

}