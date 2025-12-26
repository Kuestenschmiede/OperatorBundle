<?php

/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\MapsBundle\Classes\Events\LoadLayersEvent;
use con4gis\MapsBundle\Classes\Services\LayerService;
use con4gis\MapsBundle\Resources\contao\models\C4gMapSettingsModel;
use con4gis\MapsBundle\Resources\contao\models\C4gMapsModel;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataDirectoryModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataTypeModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LoadLayersListener
{
    private LayerService $layerService;
    private Database $database;

    /** @var array<string,array> Cached data maps */
    private array $cache = [
        'types' => [],
        'locStyles' => [],
        'tags' => [],
        'elements' => [],
        'elementTypes' => [],
        'osmAreas' => [],
        'propertyCache' => []
    ];

    /** @var string SQL condition for published elements */
    private string $publishedCondition;

    public function __construct(LayerService $layerService)
    {
        $this->layerService = $layerService;
        $this->database = Database::getInstance();
        $this->publishedCondition = $this->buildPublishedCondition();
    }

    private function buildPublishedCondition(): string 
    {
        $currentTime = time();
        return " AND ({{table}}.publishFrom IS NULL OR {{table}}.publishFrom < $currentTime) " .
               "AND ({{table}}.publishUntil IS NULL OR {{table}}.publishUntil > $currentTime)";
    }

    private function resolvePath($value): string
    {
        if (!$value) {
            return '';
        }

        $path = '';
        if (is_string($value)) {
            if (strpos($value, 'files/') === 0 || strpos($value, 'assets/') === 0 || strpos($value, 'bundles/') === 0) {
                $path = $value;
            } elseif (strlen($value) === 16) {
                $objFile = FilesModel::findByUuid($value);
                if ($objFile !== null && $objFile->path) {
                    $path = $objFile->path;
                }
            } else {
                try {
                    if (Validator::isUuid($value) || Validator::isStringUuid($value)) {
                        $objFile = FilesModel::findByUuid($value);
                        if ($objFile !== null && $objFile->path) {
                            $path = $objFile->path;
                        }
                    }
                } catch (\Exception $e) {}
            }
        }

        if ($path) {
            return (strpos($path, '/') === 0) ? $path : '/' . $path;
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Preload commonly used data to reduce database queries
     */
    private function preloadData(array $elementIds = []): void
    {
        if (empty($this->cache['tags'])) {
            // Load tags
            $tagStatement = $this->database->execute("SELECT uuid FROM tl_gutesio_data_tag");
            while ($tag = $tagStatement->fetchAssoc()) {
                $this->cache['tags'][$tag['uuid']] = true;
            }
        }

        if (empty($elementIds)) {
            return;
        }

        $idCondition = " WHERE elementId IN ('" . implode("','", $elementIds) . "')";

        // Load tag-element assignments
        $tagElementsStatement = $this->database->execute(
            "SELECT tagId, elementId FROM tl_gutesio_data_tag_element" . $idCondition
        );
        while ($row = $tagElementsStatement->fetchAssoc()) {
            if (isset($this->cache['tags'][$row['tagId']])) {
                $this->cache['elementTags'][$row['elementId']][] = $row['tagId'];
            }
        }

        if (isset($this->cache['elementTags'])) {
            foreach ($this->cache['elementTags'] as &$tags) {
                sort($tags);
            }
        }

        // Load element types and their styles
        $elementTypesStatement = $this->database->execute(
            "SELECT typeElem.elementId, typeElem.typeId, type.locstyle, type.loctype, type.showLinkedElements,
                    type.uuid as type_uuid, type.name as type_name,
                    ls.name as style_name, ls.icon_src, ls.svgSrc
             FROM tl_gutesio_data_element_type AS typeElem
             INNER JOIN tl_gutesio_data_type AS type ON typeElem.typeId = type.uuid
             LEFT JOIN tl_c4g_map_locstyles AS ls ON type.locstyle = ls.id" .
             str_replace('elementId', 'typeElem.elementId', $idCondition) .
             " ORDER BY typeElem.rank ASC"
        );
        
        while ($elementType = $elementTypesStatement->fetchAssoc()) {
            $this->cache['elementTypes'][$elementType['elementId']][] = $elementType['typeId'];
            
            $icon = $this->resolvePath($elementType['icon_src'] ?: $elementType['svgSrc']);
            if (strpos($icon, '/') !== 0 && $elementType['style_name']) {
                $styleName = $elementType['style_name'];
                if (strpos($styleName, 'io_') === 0) {
                    $styleName = substr($styleName, 3);
                }
                $styleName = str_replace(' ', '_', $styleName);
                $projectDir = System::getContainer()->getParameter('kernel.project_dir');
                $basePaths = glob($projectDir . '/files/con4gis_import_data/*/icons/Kategorie_Icons', GLOB_ONLYDIR);
                $basePath = !empty($basePaths) ? str_replace($projectDir . '/', '', $basePaths[0]) . '/' : 'files/con4gis_import_data/60/icons/Kategorie_Icons/';
                foreach (['.svg', '.png', '.jpg'] as $ext) {
                    if (file_exists($projectDir . '/' . $basePath . $styleName . $ext)) {
                        $icon = '/' . $basePath . $styleName . $ext;
                        break;
                    }
                }
            }
            
            // Pre-cache the first (highest rank) location style for each element
            if (!isset($this->cache['locStyles'][$elementType['elementId']])) {
                $this->cache['locStyles'][$elementType['elementId']] = [
                    'locstyle' => $elementType['locstyle'],
                    'loctype' => ($elementType['loctype'] === 'POI' ? 'Point' : $elementType['loctype']),
                    'icon' => $icon,
                    'styletype' => ($icon ? (strpos($icon, '.svg') !== false ? 'cust_icon_svg' : 'cust_icon') : '')
                ];
            }

            if (!isset($this->cache['types'][$elementType['typeId']])) {
                $this->cache['types'][$elementType['typeId']] = [
                    'uuid' => $elementType['type_uuid'],
                    'name' => $elementType['type_name'],
                    'locstyle' => $elementType['locstyle'],
                    'loctype' => ($elementType['loctype'] === 'POI' ? 'Point' : $elementType['loctype']),
                    'showLinkedElements' => $elementType['showLinkedElements'],
                    'icon' => $icon,
                    'styletype' => ($icon ? (strpos($icon, '.svg') !== false ? 'cust_icon_svg' : 'cust_icon') : '')
                ];
            }
        }
    }

    private function getElement(string $uuid, bool $withPublishedCondition=false): ?array
    {
        if (!isset($this->cache['elements'][$uuid])) {
            $strPublishedElem = $withPublishedCondition ? str_replace('{{table}}', 'elem', $this->publishedCondition) : "";
            $result = $this->database->prepare(
                "SELECT uuid, name, geox, geoy, showcaseIds FROM tl_gutesio_data_element WHERE uuid = ?" . $strPublishedElem
            )->execute($uuid)->fetchAssoc();
            
            if (!$result) {
                return null;
            }
            $this->cache['elements'][$uuid] = $result;
        }
        
        return $this->cache['elements'][$uuid];
    }

    private function getLocationStyle(string $elementId): ?array
    {
        return $this->cache['locStyles'][$elementId] ?? null;
    }

    public function onLoadLayersLoadElement(
        LoadLayersEvent $event,
        string $eventName,
        EventDispatcherInterface $eventDispatcher
    ): void {
        $dataLayer = $event->getLayerData();
        if ($dataLayer['type'] !== 'gutesElem') {
            return;
        }

        $alias = $this->extractAliasFromReferer();
        if (!$alias) {
            return;
        }

        $event->setPreventCaching(true);
        $elem = $this->resolveElement($alias);
        if (!$elem) {
            return;
        }

        $elementIds = array_keys($this->cache['elements']);
        $this->preloadData($elementIds);
        $dataLayer['type'] = 'GeoJSON';
        $dataLayer['format'] = 'GeoJSON';
        $type = $this->getElementType($elem['uuid']) ?: [];
        $childElements = $this->loadChildElements($elem, $type, $dataLayer);

        if ($childElements && count($childElements) > 1 && ($type['showLinkedElements'] ?? false)) {
            $dataLayer['childs'] = $childElements;
        } else {
            $createdElement = $this->createElement($elem, $dataLayer, $type, $childElements, false, true, [], true, true);
            if ($createdElement) {
                $dataLayer['childs'] = [$createdElement];
            }
        }

        $area = $this->addArea($elem, $dataLayer);
        if ($area) {
            $dataLayer['childs'][] = $area;
        }

        $event->setLayerData($dataLayer);
    }

    public function onLoadLayersLoadPart(
        LoadLayersEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher
    ): void {
        $dataLayer = $event->getLayerData();
        if ($dataLayer['type'] !== 'gutesPart') {
            return;
        }

        $objDataLayer = C4gMapsModel::findByPk($dataLayer['id']);
        if (!$objDataLayer) {
            return;
        }

        $dataLayer['type'] = 'GeoJSON';
        $dataLayer['format'] = 'GeoJSON';
        $types = $this->loadTypes($objDataLayer);
        $skipElements = StringUtil::deserialize($objDataLayer->skipElements, true);
        $sameElements = [];
        $typeElements = $this->processTypes($types, $dataLayer, $skipElements, $sameElements, $objDataLayer);
        
        $activeTypes = StringUtil::deserialize($objDataLayer->activeTypes, true);

        if ($objDataLayer->skipTypes) {
            $childs = [];
            foreach ($typeElements as $typeId => $typeElement) {
                $hide = !empty($activeTypes) && !in_array($typeId, $activeTypes);
                $show = !empty($activeTypes) && in_array($typeId, $activeTypes);
                foreach ($typeElement['childs'] as $child) {
                    $child['pid'] = $dataLayer['id'];
                    if ($hide) {
                        $child['data_hidelayer'] = '1';
                        $child['hide'] = '1';
                    } elseif ($show) {
                        $child['data_hidelayer'] = '';
                        $child['hide'] = '';
                    }
                    $childs[] = $child;
                }
            }
            $dataLayer['childs'] = $childs;
        } else {
            if (!empty($activeTypes)) {
                foreach ($typeElements as $typeId => &$typeElement) {
                    if (!in_array($typeId, $activeTypes)) {
                        $typeElement['data_hidelayer'] = '1';
                        $typeElement['hide'] = '1';
                        foreach ($typeElement['childs'] as &$child) {
                            $child['data_hidelayer'] = '1';
                            $child['hide'] = '1';
                        }
                    } else if (in_array($typeId, $activeTypes)) {
                        $typeElement['data_hidelayer'] = '';
                        $typeElement['hide'] = '';
                        foreach ($typeElement['childs'] as &$child) {
                            $child['data_hidelayer'] = '';
                            $child['hide'] = '';
                        }
                    }
                }
            }
            $dataLayer['childs'] = array_values($typeElements);
        }

        $dataLayer['initial_opened'] = $objDataLayer->initial_opened;
        $dataLayer['data_hidelayer'] = $objDataLayer->data_hidelayer;
        $dataLayer['hide'] = $objDataLayer->data_hidelayer;
        $dataLayer['display'] = true;
        $event->setLayerData($dataLayer);
    }

    private function loadTypes($objDataLayer): array
    {
        $configuredTypes = StringUtil::deserialize($objDataLayer->types, true);
        if ($configuredTypes) {
            $types = $this->database->prepare(
                "SELECT * FROM tl_gutesio_data_type WHERE uuid IN ('" . implode("','", $configuredTypes) . "')"
            )->execute()->fetchAllAssoc();
            
            return array_map(function($row) {
                return (object) ['uuid' => $row['uuid'], 'name' => $row['name'], 'row' => function() use ($row) { return $row; }];
            }, $types);
        }

        $types = $this->database->execute(
            "SELECT * FROM tl_gutesio_data_type ORDER BY name ASC"
        )->fetchAllAssoc();

        return array_map(function($row) {
            return (object) ['uuid' => $row['uuid'], 'name' => $row['name'], 'row' => function() use ($row) { return $row; }];
        }, $types);
    }

    private function processTypes(array $types, array $dataLayer, array $skipElements, array &$sameElements, $objDataLayer): array
    {
        $typeElements = [];
        $strPublishedElem = str_replace('{{table}}', 'elem', $this->publishedCondition);

        // Batch load all elements for all types at once
        $typeUuids = array_map(function($type) { return $type->uuid; }, $types);
        $query = 'SELECT elem.uuid, elem.name, elem.geox, elem.geoy, elem.showcaseIds, typeElem.typeId FROM tl_gutesio_data_element AS elem
                 INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.elementId = elem.uuid
                 WHERE typeElem.typeId IN (\'' . implode("','", $typeUuids) . '\')' . $strPublishedElem . ' ORDER BY elem.name ASC';
        
        $statement = $this->database->execute($query);
        $showcaseIdsToLoad = [];
        $elementsByType = [];
        
        while ($elem = $statement->fetchAssoc()) {
            $this->cache['elements'][$elem['uuid']] = $elem;
            $elementsByType[$elem['typeId']][] = $elem['uuid'];
            if ($elem['showcaseIds']) {
                $ids = StringUtil::deserialize($elem['showcaseIds'], true);
                foreach ($ids as $id) {
                    if (!isset($this->cache['elements'][$id])) {
                        $showcaseIdsToLoad[] = $id;
                    }
                }
            }
        }
        
        // Batch load showcase elements if needed
        if (!empty($showcaseIdsToLoad)) {
            $showcaseResult = $this->database->execute(
                "SELECT elem.uuid, elem.name, elem.geox, elem.geoy, elem.showcaseIds, typeElem.typeId 
                 FROM tl_gutesio_data_element AS elem
                 LEFT JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.elementId = elem.uuid
                 WHERE elem.uuid IN ('" . implode("','", array_unique($showcaseIdsToLoad)) . "')
                 ORDER BY typeElem.rank ASC"
            )->fetchAllAssoc();
            foreach ($showcaseResult as $showcaseElem) {
                if (!isset($this->cache['elements'][$showcaseElem['uuid']])) {
                    $this->cache['elements'][$showcaseElem['uuid']] = $showcaseElem;
                }
            }
            unset($showcaseResult);
        }

        $this->preloadData(array_keys($this->cache['elements']));
        
        $allElements = $this->cache['elements'];
        $this->cache['elements'] = [];

        foreach ($types as $type) {
            $typeData = ($type->row)();
            $elementUuids = $elementsByType[$typeData['uuid']] ?? [];
            unset($elementsByType[$typeData['uuid']]);
            
            if ($skipElements) {
                $elementUuids = array_filter($elementUuids, function($uuid) use ($skipElements) {
                    return !in_array($uuid, $skipElements);
                });
            }

            if (empty($elementUuids)) {
                continue;
            }

            $elements = [];
            foreach ($elementUuids as $uuid) {
                if (isset($allElements[$uuid])) {
                    $elements[] = $allElements[$uuid];
                    unset($allElements[$uuid]);
                }
            }
            unset($elementUuids);

            $processedElements = $this->processTypeElements($elements, $dataLayer, $typeData, $sameElements, $objDataLayer);
            unset($elements);
            
            // Remove elements from allElements that are no longer needed
            // If an element belongs to multiple types, we can't easily remove it here
            // but we can try to clear allElements once we are done if we know it's not needed anymore.
            
            if (!empty($processedElements)) {
                $typeElements[$typeData['uuid']] = [
                    'pid' => $objDataLayer->id,
                    'id' => $typeData['uuid'],
                    'name' => $typeData['name'],
                    'layername' => $typeData['name'],
                    'type' => $dataLayer['type'],
                    'format' => $dataLayer['format'],
                    'childs' => $processedElements,
                    'data_hidelayer' => $objDataLayer->data_hidelayer,
                    'hide' => $objDataLayer->data_hidelayer,
                    'initial_opened' => $objDataLayer->initial_opened,
                    'display' => true
                ];
                unset($processedElements);
            }
        }

        unset($elementsByType);
        unset($allElements);
        return $typeElements;
    }


    private function processTypeElements(array &$elements, array &$dataLayer, array &$type, array &$sameElements, $objDataLayer): array
    {
        $processedElements = [];
        $limit = $objDataLayer->be_optimize_checkboxes_limit ?: 3;
        $hideInStarboard = $objDataLayer->hideInStarboard ?: count($elements) > $limit;
        $initialOpened = $objDataLayer->initial_opened ? true : false;
        $showLinked = $type['showLinkedElements'] && count($elements) < 50;

        foreach ($elements as $key => $elem) {
            $childElements = [];
            if ($showLinked) {
                $childElements = $this->loadChildElements($elem, $type, $dataLayer);
            }
            $createdElement = $this->createElement(
                $elem, 
                $dataLayer, 
                $type, 
                $childElements, 
                true, 
                false, 
                [], 
                $hideInStarboard,
                $initialOpened
            );
            if ($createdElement) {
                $processedElements[] = $createdElement;
            }
            unset($elements[$key]);
            unset($childElements);
        }

        return $processedElements;
    }

    private function shouldCreateElement(string $elemUuid, string $typeUuid, array $checkDuplicates): bool
    {
        if (!isset($checkDuplicates[$elemUuid])) {
            return true;
        }
        
        return !in_array($typeUuid, $checkDuplicates[$elemUuid]);
    }

    public function onLoadLayersLoadDirectories(
        LoadLayersEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher
    ): void {
        $dataLayer = $event->getLayerData();
        if ($dataLayer['type'] !== 'gutes') {
            return;
        }

        $objDataLayer = C4gMapsModel::findByPk($dataLayer['id']);
        if (!$objDataLayer) {
            return;
        }

        $dataLayer['type'] = 'GeoJSON';
        $dataLayer['format'] = 'GeoJSON';
        $this->processShareSettings($dataLayer, $objDataLayer);
        
        $directories = $this->loadDirectories($objDataLayer);
        $processedDirectories = $this->processDirectories($directories, $dataLayer, $objDataLayer);
        
        $dataLayer['childs'] = $processedDirectories;
        $dataLayer['initial_opened'] = $objDataLayer->initial_opened;
        $dataLayer['data_hidelayer'] = $objDataLayer->data_hidelayer;
        $dataLayer['hide'] = $objDataLayer->data_hidelayer;
        $dataLayer['display'] = true;
        $event->setLayerData($dataLayer);
    }

    private function processShareSettings(array &$dataLayer, $objDataLayer): void
    {
        if (!$objDataLayer->popup_share_button) {
            return;
        }

        $shareMethods = StringUtil::deserialize($objDataLayer->popup_share_type, true);
        $shareDest = $objDataLayer->popup_share_destination;
        $shareBaseUrl = $this->getShareBaseUrl($shareDest, $objDataLayer);

        $dataLayer['popup_share'] = [
            'methods' => $shareMethods,
            'baseUrl' => $shareBaseUrl,
            'destType' => $shareDest,
            'additionalMessage' => $objDataLayer->popup_share_message
        ];
    }

    private function getShareBaseUrl(string $shareDest, $objDataLayer): string
    {
        switch ($shareDest) {
            case "con4gis_map":
            case "con4gis_routing":
                return "";
            case "con4gis_map_external":
            case "con4gis_routing_external":
            case "osm":
            case "osm_routing":
                return $objDataLayer->popup_share_external_link;
            case "google_map":
            case "google_map_routing":
                return "https://www.google.com/maps/dir/";
            default:
                return "";
        }
    }

    private function loadDirectories($objDataLayer): array
    {
        $configuredDirectories = StringUtil::deserialize($objDataLayer->directories, true);
        if ($configuredDirectories) {
            $directories = [];
            foreach ($configuredDirectories as $directoryId) {
                $directory = GutesioDataDirectoryModel::findOneBy('uuid', $directoryId);
                if ($directory) {
                    $directories[] = $directory->row(); // Konvertiere zu Array
                }
            }
            return $directories;
        }

        $result = GutesioDataDirectoryModel::findAll([
            'order' => "tl_gutesio_data_directory.name ASC"
        ]);

        return $result ? $result->fetchAll() : [];
    }

    private function processDirectories(array $directories, array $dataLayer, $objDataLayer): array
    {
        $processedDirectories = [];
        $typeElements = [];
        $skipElements = StringUtil::deserialize($objDataLayer->skipElements, true);

        // Batch load all elements for all directories at once
        $directoryUuids = array_filter(array_map(function($dir) { return $dir['uuid'] ?? null; }, $directories));
        if (empty($directoryUuids)) {
            return [];
        }

        $query = 'SELECT elem.uuid, elem.name, elem.geox, elem.geoy, elem.showcaseIds, type.uuid AS typeId, type.name AS typeName, dirType.directoryId AS directoryId 
                 FROM tl_gutesio_data_type AS type
                 INNER JOIN tl_gutesio_data_directory_type AS dirType ON dirType.typeId = type.uuid
                 INNER JOIN tl_gutesio_data_element_type AS elemType ON dirType.typeId = elemType.typeId
                 INNER JOIN tl_gutesio_data_element AS elem ON elemType.elementId = elem.uuid
                 WHERE dirType.directoryId IN (\'' . implode("','", $directoryUuids) . '\')
                 ORDER BY type.name ASC';
        
        $statement = $this->database->execute($query);
        $showcaseIdsToLoad = [];
        $elementsByDirectory = [];
        
        while ($elem = $statement->fetchAssoc()) {
            $this->cache['elements'][$elem['uuid']] = $elem;
            $elementsByDirectory[$elem['directoryId']][] = $elem['uuid'];
            if ($elem['showcaseIds']) {
                $ids = StringUtil::deserialize($elem['showcaseIds'], true);
                foreach ($ids as $id) {
                    if (!isset($this->cache['elements'][$id])) {
                        $showcaseIdsToLoad[] = $id;
                    }
                }
            }
        }
        
        // Batch load showcase elements if needed
        if (!empty($showcaseIdsToLoad)) {
            $showcaseResult = $this->database->execute(
                "SELECT elem.uuid, elem.name, elem.geox, elem.geoy, elem.showcaseIds, typeElem.typeId 
                 FROM tl_gutesio_data_element AS elem
                 LEFT JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.elementId = elem.uuid
                 WHERE elem.uuid IN ('" . implode("','", array_unique($showcaseIdsToLoad)) . "')
                 ORDER BY typeElem.rank ASC"
            )->fetchAllAssoc();
            foreach ($showcaseResult as $showcaseElem) {
                if (!isset($this->cache['elements'][$showcaseElem['uuid']])) {
                    $this->cache['elements'][$showcaseElem['uuid']] = $showcaseElem;
                }
            }
            unset($showcaseResult);
        }

        $this->preloadData(array_keys($this->cache['elements']));
        
        $allElements = $this->cache['elements'];
        $this->cache['elements'] = [];

        foreach ($directories as $directory) {
            $directoryUuid = $directory['uuid'] ?? null;
            $directoryName = $directory['name'] ?? '';

            if (!$directoryUuid) {
                continue;
            }

            $elementUuids = $elementsByDirectory[$directoryUuid] ?? [];
            unset($elementsByDirectory[$directoryUuid]);
            
            $elements = [];
            foreach ($elementUuids as $uuid) {
                if (isset($allElements[$uuid])) {
                    $elements[] = $allElements[$uuid];
                    unset($allElements[$uuid]);
                }
            }
            unset($elementUuids);

            $validTypes = $this->processDirectoryElements(
                $elements,
                $dataLayer,
                $skipElements,
                $typeElements,
                $directoryUuid,
                $objDataLayer
            );
            unset($elements);

            if (!empty($validTypes)) {
                $processedDirectories[] = [
                    'pid' => $dataLayer['id'],
                    'id' => $directoryUuid,
                    'name' => $directoryName,
                    'layername' => $directoryName,
                    'type' => $dataLayer['type'],
                    'format' => $dataLayer['format'],
                    'childs' => array_values($validTypes),
                    'data_hidelayer' => $objDataLayer->data_hidelayer,
                    'hide' => $objDataLayer->data_hidelayer,
                    'initial_opened' => $objDataLayer->initial_opened,
                    'display' => true
                ];
            }
            unset($elements);
        }

        unset($elementsByDirectory);
        unset($typeElements);
        unset($allElements);
        return $processedDirectories;
    }


    private function processDirectoryElements(
        array &$elements, 
        array &$dataLayer, 
        array &$skipElements, 
        array &$typeElements, 
        string $directoryId, 
        $objDataLayer
    ): array {
        $validTypes = [];
        $elementsByCategory = [];
        
        foreach ($elements as $key => $elem) {
            if ($skipElements && in_array($elem['uuid'], $skipElements)) {
                unset($elements[$key]);
                continue;
            }
            $elementsByCategory[$elem['typeId']][] = $elem;
            unset($elements[$key]);
        }

        $hideInStarboard = $objDataLayer->hideInStarboard ?: false;
        $initialOpened = $objDataLayer->initial_opened ? true : false;
        $showLinked = count($elements) < 50;
        $activeTypes = StringUtil::deserialize($objDataLayer->activeTypes, true);

        foreach ($elementsByCategory as $typeId => &$categoryElements) {
            $typeKey = $directoryId . $typeId;
            $firstElem = $categoryElements[0];
            
            if (!isset($typeElements[$typeKey])) {
                $typeElements[$typeKey] = [
                    'pid' => $directoryId,
                    'id' => $typeKey,
                    'name' => $firstElem['typeName'],
                    'layername' => $firstElem['typeName'],
                    'type' => $dataLayer['type'],
                    'format' => $dataLayer['format'],
                    'childs' => [],
                    'data_hidelayer' => $objDataLayer->data_hidelayer,
                    'hide' => $objDataLayer->data_hidelayer,
                    'initial_opened' => $objDataLayer->initial_opened,
                    'display' => true
                ];
                
                if (!empty($activeTypes) && !in_array($typeId, $activeTypes)) {
                    $typeElements[$typeKey]['data_hidelayer'] = '1';
                    $typeElements[$typeKey]['hide'] = '1';
                } else if (!empty($activeTypes) && in_array($typeId, $activeTypes)) {
                    $typeElements[$typeKey]['data_hidelayer'] = '';
                    $typeElements[$typeKey]['hide'] = '';
                }

                $validTypes[$typeKey] = &$typeElements[$typeKey];
            }

            $type = $this->cache['types'][$typeId] ?? ['uuid' => $typeId];
            $canShowLinked = $showLinked && isset($type['showLinkedElements']) && $type['showLinkedElements'];
            $parentInfo = ['id' => $typeKey];
            $forceHide = ($typeElements[$typeKey]['hide'] ?? '0') === '1';
            $forceShow = !empty($activeTypes) && in_array($typeId, $activeTypes);

            foreach ($categoryElements as $cKey => $elem) {
                $childElements = [];
                if ($canShowLinked) {
                    $childElements = $this->loadChildElements($elem, $type, $dataLayer);
                }
                
                $createdElement = $this->createElement($elem, $dataLayer, $parentInfo, $childElements, true, false, [], $hideInStarboard, $initialOpened);
                if ($createdElement) {
                    if ($forceHide) {
                        $createdElement['data_hidelayer'] = '1';
                        $createdElement['hide'] = '1';
                    } elseif ($forceShow) {
                        $createdElement['data_hidelayer'] = '';
                        $createdElement['hide'] = '';
                    }
                    $typeElements[$typeKey]['childs'][] = $createdElement;
                }
                unset($categoryElements[$cKey]);
                unset($childElements);
            }
            unset($parentInfo);
            unset($categoryElements);
            unset($type);
        }

        unset($elementsByCategory);
        return $validTypes;
    }


    private function createElement(
        array &$objElement, 
        array &$dataLayer, 
        array &$parent, 
        array $childElements = [], 
        bool $withPopup = true, 
        bool $layerStyle = false, 
        array $sameElements = [],
        bool $hideInStarboard = false,
        bool $forceInitialOpen = false
    ): array {
        if (empty($objElement['uuid'])) {
            return [];
        }

        $objLocstyle = $this->getLocationStyle($objElement['uuid']);
        $tagUuids = $this->getElementTags($objElement['uuid']);
        $parentId = $parent['uuid'] ?? ($parent['id'] ?? '');

        $properties = [];
        if ($tagUuids || $parentId) {
            $cacheKey = substr(md5($parentId . ($tagUuids ? implode(',', $tagUuids) : '')), 0, 16);
            if (isset($this->cache['propertyCache'][$cacheKey])) {
                $properties = $this->cache['propertyCache'][$cacheKey];
            } else {
                if ($tagUuids) {
                    $properties = array_fill_keys($tagUuids, true);
                    if ($parentId) {
                        $properties[$parentId] = true;
                    }
                } elseif ($parentId) {
                    $properties = [$parentId => true];
                }
                $this->cache['propertyCache'][$cacheKey] = $properties;
                $properties = $this->cache['propertyCache'][$cacheKey];
            }
        }
        unset($tagUuids);

        $element = $this->buildBaseElement($objElement, $parentId, $dataLayer, $layerStyle, $objLocstyle, $properties, $childElements, $hideInStarboard, $forceInitialOpen);

        if ($withPopup) {
            $properties['popup'] = [
                'async' => true,
                'routing_link' => true,
                'content' => 'showcase::' . $objElement['uuid']
            ];
            $element['popup'] = &$properties['popup'];
        }

        if (!$hideInStarboard) {
            if (isset($dataLayer['popup_share']) && $dataLayer['popup_share']) {
                $properties['popup_share'] = $dataLayer['popup_share'];
                $element['popup_share'] = &$properties['popup_share'];
            }
        }

        if (($objElement['geox'] && $objElement['geoy']) || ($objLocstyle && in_array($objLocstyle['loctype'], ['Editor', 'LineString', 'Polygon']))) {
            $this->addGeometryData($element, $objElement, $objLocstyle, $properties, $layerStyle, $dataLayer);
        }
        unset($properties);

        // Only merge essential data from dataLayer to reduce payload
        if (isset($dataLayer['locstyle']) && !isset($element['locstyle']) && $dataLayer['locstyle'] !== ($objLocstyle['locstyle'] ?? null)) {
            $element['locstyle'] = $dataLayer['locstyle'];
        }
        if (isset($dataLayer['type']) && !isset($element['type'])) {
            $element['type'] = $dataLayer['type'];
        }
        unset($objLocstyle);

        return $element;
    }

    private function getElementTags(string $elementId): array
    {
        return $this->cache['elementTags'][$elementId] ?? [];
    }

    private function buildBaseElement(
        array &$objElement, 
        string $parentId, 
        array &$dataLayer, 
        bool $layerStyle, 
        ?array $objLocstyle, 
        array &$properties, 
        array $childElements,
        bool $hideInStarboard = false,
        bool $forceInitialOpen = false
    ): array {
        $elementId = substr(md5($objElement['uuid'] . $parentId), 0, 16);
        $name = $objElement['name'];
        if (strpos($name, '&') !== false) {
            $name = html_entity_decode($name);
        }
        $element = [
            'id' => $elementId,
            'name' => $name,
            'type' => $dataLayer['type'] ?? 'GeoJSON',
            'format' => $dataLayer['format'] ?? 'GeoJSON',
            'childs' => $childElements ?: [],
            'content' => []
        ];

        if ($parentId) {
            $element['pid'] = $parentId;
        }

        $style = $layerStyle ? ($dataLayer['locstyle'] ?? null) : ($objLocstyle['locstyle'] ?? null);
        if ($style && (!isset($dataLayer['locstyle']) || $style !== $dataLayer['locstyle'])) {
            $element['locstyle'] = $style;
        }

        if ($hideInStarboard) {
            $element['hideInStarboard'] = true;
        }

        if ($properties) {
            $element['tags'] = &$properties;
        }

        if ($objLocstyle && isset($objLocstyle['loctype']) && $objLocstyle['loctype'] !== 'Point') {
            $element['loctype'] = $objLocstyle['loctype'];
        }

        if (!$hideInStarboard) {
            $element['display'] = true;
        }

        if ($forceInitialOpen) {
            $element['initial_opened'] = '1';
        } elseif (isset($dataLayer['data_hidelayer']) && $dataLayer['data_hidelayer']) {
            $element['data_hidelayer'] = '1';
            $element['hide'] = '1';
        }

        return $element;
    }

    private function addGeometryData(
        array &$element, 
        array &$objElement, 
        ?array &$objLocstyle, 
        array &$properties, 
        bool $layerStyle, 
        array &$dataLayer
    ): void {
        if ($objLocstyle && in_array($objLocstyle['loctype'], ['Editor', 'LineString', 'Polygon'])) {
            $this->addComplexGeometry($element, $objElement, $properties, $layerStyle, $dataLayer, $objLocstyle);
        } else {
            $this->addSimpleGeometry($element, $objElement, $properties, $layerStyle, $dataLayer, $objLocstyle);
        }

        if (!($element['hideInStarboard'] ?? false)) {
            $element['excludeFromSingleLayer'] = true;
        }
    }

    private function addComplexGeometry(
        array &$element, 
        array &$objElement, 
        array &$properties, 
        bool $layerStyle, 
        array &$dataLayer, 
        ?array &$objLocstyle
    ): void {
        $geojson = $objElement['geojson'] ?? null;
        if (!$geojson && $objElement['uuid']) {
            $dbRes = $this->database->prepare("SELECT geojson FROM tl_gutesio_data_element WHERE uuid = ?")
                ->execute($objElement['uuid'])->fetchAssoc();
            $geojson = $dbRes['geojson'] ?? null;
        }

        if ($geojson) {
            $element['excludeFromSingleLayer'] = '1';
            if (strpos($geojson, 'FeatureCollection') === false) {
                 $geojson = '{"type": "FeatureCollection", "features": ' . $geojson . '}';
            }
                
            $data = json_decode($geojson, true);
            unset($geojson);
            
            if (!$data) {
                $data = ['type' => 'FeatureCollection', 'features' => []];
            }

            if (!isset($data['features']) && ($data['type'] ?? '') === 'FeatureCollection') {
                $data['features'] = [];
            }

            if (isset($data['features'])) {
                $featureProperties = $properties;
                $featureProperties['zindex'] = -5;
                if ($objLocstyle && isset($objLocstyle['icon']) && $objLocstyle['icon']) {
                    $featureProperties['icon_src'] = $objLocstyle['icon'];
                    $featureProperties['styletype'] = $objLocstyle['styletype'] ?: (strpos($objLocstyle['icon'], '.svg') !== false ? 'cust_icon_svg' : 'cust_icon');
                }
                foreach ($data['features'] as $key => $feature) {
                    $data['features'][$key]['properties'] = &$featureProperties;
                }
                unset($featureProperties);
            }
            
            $data['properties'] = &$properties;
            
            $locstyle = $layerStyle ? ($dataLayer['locstyle'] ?? null) : ($objLocstyle['locstyle'] ?? null);
            $element['content'] = [[
                'data' => &$data,
                'type' => $dataLayer['type'] ?? 'GeoJSON',
                'format' => $dataLayer['format'] ?? 'GeoJSON'
            ]];

            if ($locstyle && (!isset($dataLayer['locstyle']) || $locstyle !== $dataLayer['locstyle'])) {
                $element['content'][0]['locationStyle'] = $locstyle;
            }
            unset($data);
        }
    }

    private function addSimpleGeometry(
        array &$element, 
        array &$objElement, 
        array &$properties, 
        bool $layerStyle, 
        array &$dataLayer, 
        ?array &$objLocstyle
    ): void {
        $locstyle = $layerStyle ? ($dataLayer['locstyle'] ?? null) : ($objLocstyle['locstyle'] ?? null);

        $featureProperties = &$properties;
        if ($objLocstyle && isset($objLocstyle['icon']) && $objLocstyle['icon']) {
            $featureProperties['icon_src'] = $objLocstyle['icon'];
            $featureProperties['styletype'] = $objLocstyle['styletype'] ?: (strpos($objLocstyle['icon'], '.svg') !== false ? 'cust_icon_svg' : 'cust_icon');
        }

        $element['content'] = [[
            'data' => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$objElement['geox'], (float)$objElement['geoy']]
                ],
                'properties' => $featureProperties
            ],
            'type' => $dataLayer['type'] ?? 'GeoJSON',
            'format' => $dataLayer['format'] ?? 'GeoJSON'
        ]];
        
        if ($locstyle && (!isset($dataLayer['locstyle']) || $locstyle !== $dataLayer['locstyle'])) {
            $element['content'][0]['locationStyle'] = $locstyle;
        }
    }

    private function extractAliasFromReferer(): ?string
    {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return null;
        }

        $referer = $_SERVER['HTTP_REFERER'];
        $parts = explode('/', $referer);
        $lastPart = end($parts);

        if (strpos($lastPart, '.html') !== false) {
            $lastPart = substr($lastPart, 0, strpos($lastPart, '.html'));
        }

        if (strpos($lastPart, '?') !== false) {
            $lastPart = explode('?', $lastPart)[0];
        }

        return $lastPart ?: null;
    }

    private function resolveElement(string $alias): ?array
    {
        if (C4GUtils::isValidGUID($alias)) {

            $offerConnections = $this->database->prepare(
                'SELECT elementId FROM tl_gutesio_data_child_connection WHERE childId = ?'
            )->execute('{' . strtoupper($alias) . '}')->fetchAllAssoc();

            if ($offerConnections && count($offerConnections) > 0) {
                return $this->getElement($offerConnections[0]['elementId']);
            }

        } else {
            // offer alias found in URL, no uuid
            $child = $this->database->prepare("SELECT uuid FROM tl_gutesio_data_child WHERE alias = ?")
                ->execute($alias)->fetchAssoc();

            if ($child) {
                $offerConnections = $this->database->prepare(
                    'SELECT elementId FROM tl_gutesio_data_child_connection WHERE childId = ?'
                )->execute($child['uuid'])->fetchAllAssoc();

                if ($offerConnections && count($offerConnections) > 0) {
                    return $this->getElement($offerConnections[0]['elementId']);
                }
            }
        }

        return $this->database->prepare(
            'SELECT uuid, name, geox, geoy, showcaseIds FROM tl_gutesio_data_element WHERE alias = ?'
        )->execute($alias)->fetchAssoc() ?: null;
    }

    private function getElementType(string $elementId): ?array
    {
        $typeIds = $this->cache['elementTypes'][$elementId] ?? [];
        if (empty($typeIds)) {
            $type = $this->database->prepare(
                'SELECT type.*, ls.name as style_name, ls.icon_src, ls.svgSrc FROM tl_gutesio_data_type AS type 
                 INNER JOIN tl_gutesio_data_element_type AS typeElem 
                 ON typeElem.typeId = type.uuid
                 LEFT JOIN tl_c4g_map_locstyles AS ls ON type.locstyle = ls.id
                 WHERE typeElem.elementId = ? ORDER BY typeElem.rank ASC LIMIT 1'
            )->execute($elementId)->fetchAssoc() ?: null;
            if ($type && isset($type['loctype']) && $type['loctype'] === 'POI') {
                $type['loctype'] = 'Point';
            }
            if ($type) {
                $icon = $this->resolvePath($type['icon_src'] ?: $type['svgSrc']);
                if (strpos($icon, '/') !== 0 && ($type['style_name'] ?? '')) {
                    $styleName = $type['style_name'];
                    if (strpos($styleName, 'io_') === 0) {
                        $styleName = substr($styleName, 3);
                    }
                    $styleName = str_replace(' ', '_', $styleName);
                    $projectDir = System::getContainer()->getParameter('kernel.project_dir');
                    $basePaths = glob($projectDir . '/files/con4gis_import_data/*/icons/Kategorie_Icons', GLOB_ONLYDIR);
                    $basePath = !empty($basePaths) ? str_replace($projectDir . '/', '', $basePaths[0]) . '/' : 'files/con4gis_import_data/60/icons/Kategorie_Icons/';
                    foreach (['.svg', '.png', '.jpg'] as $ext) {
                        if (file_exists($projectDir . '/' . $basePath . $styleName . $ext)) {
                            $icon = '/' . $basePath . $styleName . $ext;
                            break;
                        }
                    }
                }
                $type['icon'] = $icon;
                $type['styletype'] = $icon ? (strpos($icon, '.svg') !== false ? 'cust_icon_svg' : 'cust_icon') : '';
            }
            return $type;
        }

        $typeId = $typeIds[0];
        $type = $this->cache['types'][$typeId] ?? null;
        if ($type && isset($type['loctype']) && $type['loctype'] === 'POI') {
            $type['loctype'] = 'Point';
        }
        return $type;
    }

    private function loadChildElements(array &$elem, array &$type, array &$dataLayer): array
    {
        if (empty($elem['showcaseIds']) || empty($type['showLinkedElements'])) {
            return [];
        }

        $childElements = [];
        $showcaseIds = array_unique(StringUtil::deserialize($elem['showcaseIds'], true));

        foreach ($showcaseIds as $showcaseId) {
            $childElem = $this->getElement($showcaseId);
            if ($childElem) {
                $createdElement = $this->createElement($childElem, $dataLayer, $elem, [], false, false, [], true, false);
                if ($createdElement) {
                    $childElements[] = $createdElement;
                }
            }
        }

        return $childElements;
    }

    private function addArea($objElement, $layer): ?array
    {
        $settings = C4gMapSettingsModel::findOnly();
        if (!$settings) {
            return null;
        }
        
        $url = $settings->con4gisIoUrl;
        $key = $settings->con4gisIoKey;
        
        $zipElem = $this->database->prepare(
            'SELECT typeFieldValue as zip FROM tl_gutesio_data_type_element_values 
             WHERE elementId = ? AND typeFieldKey="surrZip"'
        )->execute($objElement['uuid'])->fetchAssoc();
        
        if (!$zipElem || !$zipElem['zip']) {
            return null;
        }

        $locstyle = $this->database->prepare(
            'SELECT areaLocstyle as locci FROM tl_c4g_maps WHERE id=?'
        )->execute($layer['id'])->fetchAssoc()['locci'];
        
        $arrPostalCodes = explode(',', $zipElem['zip']);
        sort($arrPostalCodes);
        $cacheKey = md5(implode(',', $arrPostalCodes));
        if (isset($this->cache['osmAreas'][$cacheKey])) {
            $osmData = $this->cache['osmAreas'][$cacheKey];
        } else {
            $strOvp = "[out:geojson][timeout:25];(";
            
            foreach ($arrPostalCodes as $postalCode) {
                if (preg_match("/^[0-9]{5}$/", $postalCode)) {
                    $strOvp .= 'relation[postal_code=' . $postalCode . '][boundary=postal_code];';
                }
            }
            
            $strOvp .= ');out body;>;out skel qt;';
            
            $REQUEST = new \Request();
            if (isset($_SERVER['HTTP_REFERER'])) {
                $REQUEST->setHeader('Referer', $_SERVER['HTTP_REFERER']);
            }
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $REQUEST->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT']);
            }
            
            $sendUrl = $url . "osm.php?key=" . $key . "&data=" . rawurlencode($strOvp);
            $REQUEST->send($sendUrl);
            $response = $REQUEST->response;

            if (!$response) {
                return null;
            }

            $osmData = json_decode($response);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            $this->cache['osmAreas'][$cacheKey] = $osmData;
        }

        return [
            "content" => [[
                "data" => $osmData,
                "locationStyle" => $locstyle ?: $layer['locstyle'],
                "type" => "GeoJSON",
            ]],
            "id" => 999999999,
            "pid" => $layer['id'],
            "childs" => [],
            "zIndex" => 0,
            "hideInStarboard" => "1",
            "format" => "GeoJSON",
            "locstyle" => $locstyle ?: $layer['locstyle'],
            "excludeFromSingleLayer" => true
        ];
    }
}