<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
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
        $filterElements = $modelProfile->filterElements;
        $filterHandling = $modelProfile->filterType;
        $currentFilters = $event->getFilters();

        if ($filterHandling == 1) { //Filters with tags
            $strSelect = 'SELECT * FROM tl_gutesio_data_tag WHERE published = 1';
            $tags = $this->Database->prepare($strSelect)->execute()->fetchAllAssoc();

            foreach ($tags as $tag) {
                if ($filterElements && !str_contains($filterElements, $tag['uuid'])) {
                    continue;
                }
                $filterObject = new FeatureFilter();
                $filterObject->setFieldName($tag['name']);

                $imageUuid = StringUtil::binToUuid($tag['image']);
                $file = FilesModel::findByUuid($imageUuid);
                if ($file && $file->path) {
                    $filterObject->setImage($file->path);
                }
                if ($tag['technicalKey'] === 'tag_opening_hours' || $tag['technicalKey'] === 'tag_phone_hours') {
                    $filterObject->addFilterValue([
                        'identifier' => $tag['uuid'],
                        'translation' => $tag['name'],
                        'value' => 'opening_hours',
                        'field' => $tag['technicalKey'] === 'tag_opening_hours' ? 'opening_hours': 'phoneHours',
                    ]);
                } else {
                    $filterObject->addFilterValue([
                        'identifier' => $tag['uuid'],
                        'translation' => $tag['name'],
                    ]);
                }
                $currentFilters = array_merge($currentFilters, [$filterObject]);
            }
        } elseif ($filterHandling == 2) { // filter with diretories and categories
            $modelMaps = C4gMapsModel::findOneBy('pid', $modelMaps->id); //ToDo
            $t = 'tl_gutesio_data_directory';
            $arrOptions = [
                'order' => "$t.name ASC",
            ];
            $configuredDirectories = unserialize($modelMaps->directories);
            if ($configuredDirectories) {
                $objDirectories = [];
                foreach ($configuredDirectories as $configuredDirectory) {
                    $objDirectories[] = GutesioDataDirectoryModel::findOneBy('uuid', $configuredDirectory);
                }
            } else {
                $objDirectories = GutesioDataDirectoryModel::findAll($arrOptions);
            }
            foreach ($objDirectories as $directory) {
                if ($filterElements && !str_contains($filterElements, $directory->uuid)) {
                    continue;
                }
                $filterObject = new FeatureFilter();
                $filterObject->setFieldName($directory->name);
                $strQueryTypes = 'SELECT type.* FROM tl_gutesio_data_type AS type
                INNER JOIN tl_gutesio_data_directory_type AS dirType ON dirType.typeId = type.uuid
                WHERE dirType.directoryId = ?
                ORDER BY type.name ASC';
                $arrTypes = $this->Database->prepare($strQueryTypes)->execute($directory->uuid)->fetchAllAssoc();
                $types = [];

                foreach ($arrTypes as $type) {
                    $filterObject->addFilterValue([
                        'identifier' => $type['uuid'],
                        'translation' => $type['name'],
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
