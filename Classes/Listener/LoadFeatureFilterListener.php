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

use con4gis\MapsBundle\Classes\Events\LoadFeatureFiltersEvent;
use con4gis\MapsBundle\Classes\Filter\FeatureFilter;
use con4gis\MapsBundle\Classes\Services\FilterService;
use con4gis\MapsBundle\Resources\contao\models\C4gMapProfilesModel;
use con4gis\MapsBundle\Resources\contao\models\C4gMapsModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataDirectoryModel;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LoadFeatureFilterListener
{
    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
        $this->Database = Database::getInstance();
    }
    public function onLoadFeatureFilters(
        LoadFeatureFiltersEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher
    ) {
        $mapId = $event->getProfileId();
        $modelMaps = C4gMapsModel::findById($mapId); //ToDo
        $modelProfile = C4gMapProfilesModel::findById($modelMaps->profile);
        $filterHandling = $modelProfile->filterType;
        $currentFilters = $event->getFilters();

        if ($filterHandling == 1) { //Filters with tags
            $strSelect = 'SELECT * FROM tl_gutesio_data_tag WHERE published = 1 AND availableInMap = 1';
            $tags = $this->Database->prepare($strSelect)->execute()->fetchAllAssoc();

            foreach ($tags as $tag) {
                $filterObject = new FeatureFilter();
                $filterObject->setFieldName($tag['name']);

                $imageUuid = StringUtil::binToUuid($tag['image']);
                $file = FilesModel::findByUuid($imageUuid);
                if ($file && $file->path) {
                    $filterObject->setImage($file->path);
                }
                if ($tag['technicalKey'] === "tag_opening_hours") {
                    $filterObject->addFilterValue([
                        'identifier'    => $tag['uuid'],
                        'translation'   => $tag['name'],
                        'value'         =>"opening_hours"
                    ]);
                }
                else {
                    $filterObject->addFilterValue([
                        'identifier' => $tag['uuid'],
                        'translation' => $tag['name']
                    ]);
                }
                $currentFilters = array_merge($currentFilters, [$filterObject]);
            }
        } else if ($filterHandling == 2) { // filter with diretories and categories
            $modelMaps = C4gMapsModel::findOneBy('pid', $modelMaps->id); //ToDo

            $t = 'tl_gutesio_data_directory';
            $arrOptions = [
                'order' => "$t.name ASC",
            ];
            $configuredDirectories = unserialize($modelMaps->directories);
            if ($configuredDirectories) {
                $objDirectories = [];
                foreach($configuredDirectories as $configuredDirectory) {
                    $objDirectories[] = GutesioDataDirectoryModel::findOneBy("uuid", $configuredDirectory);
                }
            } else {
                $objDirectories = GutesioDataDirectoryModel::findAll($arrOptions);
            }
            foreach ($objDirectories as $directory) {
                $filterObject = new FeatureFilter();
                $filterObject->setFieldName($directory->name);
                $strQueryTypes = "SELECT type.* FROM tl_gutesio_data_type AS type
                INNER JOIN tl_gutesio_data_directory_type AS dirType ON dirType.typeId = type.uuid
                WHERE dirType.directoryId = ?
                ORDER BY type.name ASC";
                $arrTypes = $this->Database->prepare($strQueryTypes)->execute($directory->uuid)->fetchAllAssoc();
                $types = [];

                foreach ($arrTypes as $type) {
                    $filterObject->addFilterValue([
                        'identifier' => $type['uuid'],
                        'translation' => $type['name']
                    ]);
                }

                if ($arrTypes && count($arrTypes) > 1) {
                    $currentFilters = array_merge($currentFilters, [$filterObject]);
                }
            }
        }
        $event->setFilters($currentFilters);
    }
}
