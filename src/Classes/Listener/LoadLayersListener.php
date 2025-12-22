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
use Contao\StringUtil;
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
        'elementTypes' => []
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

    /**
     * Preload commonly used data to reduce database queries
     */
    private function preloadData(): void
    {
        if (!empty($this->cache['tags'])) {
            return;
        }

        // Load tags
        $tagResult = $this->database->execute("SELECT uuid, imageCDN, name FROM tl_gutesio_data_tag")->fetchAllAssoc();
        foreach ($tagResult as $tag) {
            $this->cache['tags'][$tag['uuid']] = $tag;
        }

        // Load all tag-element assignments at once
        $tagElements = $this->database->execute(
            "SELECT tagId, elementId FROM tl_gutesio_data_tag_element"
        )->fetchAllAssoc();
        foreach ($tagElements as $row) {
            $this->cache['elementTags'][$row['elementId']][] = $row['tagId'];
        }

        // Load element types and their styles
        $elementTypesResult = $this->database->execute(
            "SELECT typeElem.elementId, typeElem.typeId, type.locstyle, type.loctype, type.showLinkedElements,
                    type.uuid as type_uuid, type.name as type_name
             FROM tl_gutesio_data_element_type AS typeElem
             INNER JOIN tl_gutesio_data_type AS type ON typeElem.typeId = type.uuid
             ORDER BY typeElem.rank ASC"
        )->fetchAllAssoc();
        
        foreach ($elementTypesResult as $elementType) {
            $this->cache['elementTypes'][$elementType['elementId']][] = $elementType['typeId'];
            
            // Pre-cache the first (highest rank) location style for each element
            if (!isset($this->cache['locStyles'][$elementType['elementId']])) {
                $this->cache['locStyles'][$elementType['elementId']] = [
                    'locstyle' => $elementType['locstyle'],
                    'loctype' => $elementType['loctype']
                ];
            }

            if (!isset($this->cache['types'][$elementType['typeId']])) {
                $this->cache['types'][$elementType['typeId']] = [
                    'uuid' => $elementType['type_uuid'],
                    'name' => $elementType['type_name'],
                    'locstyle' => $elementType['locstyle'],
                    'loctype' => $elementType['loctype'],
                    'showLinkedElements' => $elementType['showLinkedElements']
                ];
            }
        }
    }

    private function getElement(string $uuid, bool $withPublishedCondition=false): ?array
    {
        if (!isset($this->cache['elements'][$uuid])) {
            $strPublishedElem = $withPublishedCondition ? str_replace('{{table}}', 'elem', $this->publishedCondition) : "";
            $result = $this->database->prepare(
                "SELECT * FROM tl_gutesio_data_element WHERE uuid = ?" . $strPublishedElem
            )->execute($uuid)->fetchAssoc();
            
            $this->cache['elements'][$uuid] = $result ?: null;
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

        $this->preloadData();
        $dataLayer['type'] = 'GeoJSON';
        $dataLayer['format'] = 'GeoJSON';
        $type = $this->getElementType($elem['uuid']);
        $childElements = $this->loadChildElements($elem, $type, $dataLayer);

        if ($childElements && count($childElements) > 1 && $type['showLinkedElements']) {
            $dataLayer['childs'] = $childElements;
        } else {
            $dataLayer['childs'] = [$this->createElement($elem, $dataLayer, $type, $childElements, false, true, [], true, true)];
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

        $this->preloadData();
        $dataLayer['type'] = 'GeoJSON';
        $dataLayer['format'] = 'GeoJSON';
        $types = $this->loadTypes($objDataLayer);
        $skipElements = StringUtil::deserialize($objDataLayer->skipElements, true);
        $sameElements = [];
        $typeElements = $this->processTypes($types, $dataLayer, $skipElements, $sameElements, $objDataLayer);
        
        $dataLayer['childs'] = array_values($typeElements);
        $dataLayer['initial_opened'] = '';
        $dataLayer['data_hidelayer'] = '1';
        $dataLayer['hide'] = '1';
        $dataLayer['display'] = true;
        $event->setLayerData($dataLayer);
    }

    private function loadTypes($objDataLayer): array
    {
        $configuredTypes = unserialize($objDataLayer->types);
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
        $query = 'SELECT elem.*, typeElem.typeId FROM tl_gutesio_data_element AS elem
                 INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.elementId = elem.uuid
                 WHERE typeElem.typeId IN (\'' . implode("','", $typeUuids) . '\')' . $strPublishedElem . ' ORDER BY elem.name ASC';
        
        $allElements = $this->database->execute($query)->fetchAllAssoc();
        
        // Cache elements to avoid individual queries later
        $showcaseIdsToLoad = [];
        foreach ($allElements as $elem) {
            $this->cache['elements'][$elem['uuid']] = $elem;
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
                "SELECT * FROM tl_gutesio_data_element WHERE uuid IN ('" . implode("','", array_unique($showcaseIdsToLoad)) . "')"
            )->fetchAllAssoc();
            foreach ($showcaseResult as $showcaseElem) {
                $this->cache['elements'][$showcaseElem['uuid']] = $showcaseElem;
            }
        }

        $elementsByType = [];
        foreach ($allElements as $elem) {
            $elementsByType[$elem['typeId']][] = $elem;
        }

        foreach ($types as $type) {
            $typeData = $type->row();
            $elements = $elementsByType[$typeData['uuid']] ?? [];
            
            $elements = array_filter($elements, function($elem) use ($skipElements) {
                return !in_array($elem['uuid'], $skipElements);
            });

            if (empty($elements)) {
                continue;
            }

            $processedElements = $this->processTypeElements($elements, $dataLayer, $typeData, $sameElements, $objDataLayer);
            
            if (!empty($processedElements)) {
                $baseData = $dataLayer;
                unset($baseData['childs']);
                $typeElements[$typeData['uuid']] = array_merge($baseData, [
                    'pid' => $objDataLayer->id,
                    'id' => $typeData['uuid'],
                    'name' => $typeData['name'],
                    'layername' => $typeData['name'],
                    'childs' => $processedElements,
                    'zoomTo' => false,
                    'data_hidelayer' => '1',
                    'initial_opened' => false,
                ]);
            }
        }

        return $typeElements;
    }


    private function processTypeElements(array $elements, array $dataLayer, array $type, array &$sameElements, $objDataLayer): array
    {
        $processedElements = [];
        $checkDuplicates = [];
        $limit = $objDataLayer->be_optimize_checkboxes_limit ?: 3;
        $hideInStarboard = count($elements) > $limit;

        foreach ($elements as $elem) {
            $sameElements[$elem['uuid']][] = $elem;
            if ($this->shouldCreateElement($elem['uuid'], $type['uuid'], $checkDuplicates)) {
                $childElements = [];
                if ($type['showLinkedElements']) {
                    $childElements = $this->loadChildElements($elem, $type, $dataLayer);
                }
                $processedElements[] = $this->createElement(
                    $elem, 
                    $dataLayer, 
                    $type, 
                    $childElements, 
                    true, 
                    false, 
                    $sameElements[$elem['uuid']],
                    $hideInStarboard,
                    false
                );
            }
            
            $checkDuplicates[$elem['uuid']][] = $type['uuid'];
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

        $this->preloadData();
        $dataLayer['type'] = 'GeoJSON';
        $dataLayer['format'] = 'GeoJSON';
        $this->processShareSettings($dataLayer, $objDataLayer);
        
        $directories = $this->loadDirectories($objDataLayer);
        $processedDirectories = $this->processDirectories($directories, $dataLayer, $objDataLayer);
        
        $dataLayer['childs'] = $processedDirectories;
        $dataLayer['initial_opened'] = '';
        $dataLayer['data_hidelayer'] = '1';
        $dataLayer['hide'] = '1';
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

        $query = 'SELECT elem.*, type.uuid AS typeId, type.name AS typeName, dirType.directoryId AS directoryId 
                 FROM tl_gutesio_data_type AS type
                 INNER JOIN tl_gutesio_data_directory_type AS dirType ON dirType.typeId = type.uuid
                 INNER JOIN tl_gutesio_data_element_type AS elemType ON dirType.typeId = elemType.typeId
                 INNER JOIN tl_gutesio_data_element AS elem ON elemType.elementId = elem.uuid
                 WHERE dirType.directoryId IN (\'' . implode("','", $directoryUuids) . '\')
                 ORDER BY type.name ASC';
        
        $allElements = $this->database->execute($query)->fetchAllAssoc();
        
        // Cache elements to avoid individual queries later
        $showcaseIdsToLoad = [];
        foreach ($allElements as $elem) {
            $this->cache['elements'][$elem['uuid']] = $elem;
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
                "SELECT * FROM tl_gutesio_data_element WHERE uuid IN ('" . implode("','", array_unique($showcaseIdsToLoad)) . "')"
            )->fetchAllAssoc();
            foreach ($showcaseResult as $showcaseElem) {
                $this->cache['elements'][$showcaseElem['uuid']] = $showcaseElem;
            }
        }

        $elementsByDirectory = [];
        foreach ($allElements as $elem) {
            $elementsByDirectory[$elem['directoryId']][] = $elem;
        }

        foreach ($directories as $directory) {
            $directoryUuid = $directory['uuid'] ?? null;
            $directoryName = $directory['name'] ?? '';

            if (!$directoryUuid) {
                continue;
            }

            $elements = $elementsByDirectory[$directoryUuid] ?? [];
            $validTypes = $this->processDirectoryElements(
                $elements,
                $dataLayer,
                $skipElements,
                $typeElements,
                $directoryUuid,
                $objDataLayer
            );

            if (!empty($validTypes)) {
                $baseData = $dataLayer;
                unset($baseData['childs']);
                $singleDir = [
                    'pid' => $dataLayer['id'],
                    'id' => $directoryUuid,
                    'name' => $directoryName,
                    'layername' => $directoryName,
                    'childs' => array_values($validTypes),
                    'data_hidelayer' => '1',
                    'initial_opened' => false,
                    'hideInStarboard' => false,
                    'zoomTo' => false,
                ];
                $processedDirectories[] = array_merge($baseData, $singleDir);
            }
        }

        return $processedDirectories;
    }


    private function processDirectoryElements(
        array $elements, 
        array $dataLayer, 
        array $skipElements, 
        array &$typeElements, 
        string $directoryId, 
        $objDataLayer
    ): array {
        $validTypes = [];
        $elementsByCategory = [];
        
        // Remove childs from dataLayer for merging to avoid memory issues
        $baseData = $dataLayer;
        unset($baseData['childs']);

        foreach ($elements as $elem) {
            if (in_array($elem['uuid'], $skipElements)) {
                continue;
            }
            $elementsByCategory[$elem['typeId']][] = $elem;
        }

        foreach ($elementsByCategory as $typeId => $categoryElements) {
            $typeKey = $directoryId . $typeId;
            $firstElem = $categoryElements[0];
            
            if (!isset($typeElements[$typeKey])) {
                $singleType = [
                    'pid' => $directoryId,
                    'id' => $typeKey,
                    'name' => $firstElem['typeName'],
                    'layername' => $firstElem['typeName'],
                    'hideInStarboard' => false,
                    'childs' => [],
                    'zoomTo' => false,
                    'data_hidelayer' => '1',
                    'initial_opened' => false,
                ];
                $typeElements[$typeKey] = array_merge($baseData, $singleType);
                $validTypes[$typeKey] = &$typeElements[$typeKey];
            }

            foreach ($categoryElements as $elem) {
                $type = $this->cache['types'][$elem['typeId']] ?? ['uuid' => $elem['typeId']];
                $childElements = [];
                if (isset($type['showLinkedElements']) && $type['showLinkedElements']) {
                    $childElements = $this->loadChildElements($elem, $type, $dataLayer);
                }
                
                $treeElement = $this->createElement($elem, $dataLayer, ['id' => $typeKey], $childElements, true, false, [], true, false);
                $typeElements[$typeKey]['childs'][] = $treeElement;
            }
        }

        return $validTypes;
    }

    private function createElement(
        array $objElement, 
        array $dataLayer, 
        array $parent, 
        array $childElements = [], 
        bool $withPopup = true, 
        bool $layerStyle = false, 
        array $sameElements = [],
        bool $hideInStarboard = false,
        bool $forceInitialOpen = false
    ): array {
        $objLocstyle = $this->getLocationStyle($objElement['uuid']);
        $tags = $this->getElementTags($objElement['uuid']);
        $tagUuids = array_merge(
            array_combine(array_keys($tags), array_fill(0, count($tags), true)),
            [($parent['uuid'] ?? ($parent['id'] ?? '')) => true]
        );

        $element = $this->buildBaseElement($objElement, $parent, $dataLayer, $layerStyle, $objLocstyle, $tags, $childElements, $hideInStarboard, $forceInitialOpen);

        $popup = $withPopup ? [
            'async' => true,
            'content' => 'showcase::' . $objElement['uuid'],
            'routing_link' => true,
            'positionId' => $element['id'],
        ] : [];

        if (!$hideInStarboard) {
            $element['popup_share'] = $dataLayer['popup_share'] ?? null;
            // Filter out empty share settings
            if (!$element['popup_share']) {
                unset($element['popup_share']);
            }
        }

        if (($objElement['geox'] && $objElement['geoy']) || $objElement['geojson']) {
            $this->addGeometryData($element, $objElement, $objLocstyle, $popup, $tagUuids, $layerStyle, $dataLayer);
        }

        if ($popup) {
            $element['popup'] = $popup;
        }

        // Only merge essential data from dataLayer to reduce payload
        $essentialFields = ['locstyle', 'type', 'pid'];
        foreach ($essentialFields as $field) {
            if (isset($dataLayer[$field]) && !isset($element[$field])) {
                $element[$field] = $dataLayer[$field];
            }
        }

        return $element;
    }

    private function getElementTags(string $elementId): array
    {
        $elementTags = $this->cache['elementTags'][$elementId] ?? [];

        $tags = [];
        $tagUuids = [];
        foreach ($elementTags as $tagId) {
            $tag = $this->cache['tags'][$tagId] ?? null;
            if ($tag && $tag['name'] && !isset($tagUuids[$tag['uuid']])) {
                $tagUuids[$tag['uuid']] = true;
            }
        }

        return $tagUuids;
    }

    private function buildBaseElement(
        array $objElement, 
        array $parent, 
        array $dataLayer, 
        bool $layerStyle, 
        ?array $objLocstyle, 
        array $tags, 
        array $childElements,
        bool $hideInStarboard = false,
        bool $forceInitialOpen = false
    ): array {
        $name = html_entity_decode($objElement['name']);
        $elementId = $objElement['uuid'] . ($parent['uuid'] ?? ($parent['id'] ?? ''));
        $element = [
            'pid' => $parent['uuid'] ?? ($parent['id'] ?? null),
            'id' => $elementId,
            'key' => $elementId,
            'type' => 'GeoJSON',
            'projection' => $dataLayer['projection'] ?? 'EPSG:4326',
            'format' => 'GeoJSON',
            'tags' => $tags,
            'childs' => $childElements,
            'name' => $name,
            'locstyle' => $layerStyle ? $dataLayer['locstyle'] : ($objLocstyle['locstyle'] ?? null),
            'hideInStarboard' => $hideInStarboard,
        ];

        if (!$hideInStarboard) {
            $element['layername'] = $name;
            $element['zIndex'] = 2000;
            $element['zoomTo'] = false;
            $element['display'] = true;
        }

        $element['data_hidelayer'] = $forceInitialOpen ? '' : '1';
        $element['hide'] = $element['data_hidelayer'];
        $element['initial_opened'] = $forceInitialOpen ? '1' : '';

        return $element;
    }

    private function addGeometryData(
        array &$element, 
        array $objElement, 
        ?array $objLocstyle, 
        array $popup, 
        array $tagUuids, 
        bool $layerStyle, 
        array $dataLayer
    ): void {
        $properties = [
            'projection' => 'EPSG:4326',
            'popup' => $popup,
        ];
        
        if (!$element['hideInStarboard']) {
            $properties['graphicTitle'] = $objElement['name'];
        }

        $properties = array_merge($properties, $tagUuids);

        if ($objLocstyle && in_array($objLocstyle['loctype'], ['Editor', 'LineString', 'Polygon'])) {
            $this->addComplexGeometry($element, $objElement, $properties, $layerStyle, $dataLayer, $objLocstyle);
        } else {
            $this->addSimpleGeometry($element, $objElement, $properties, $layerStyle, $dataLayer, $objLocstyle);
        }

        if (!$element['hideInStarboard']) {
            $element['excludeFromSingleLayer'] = true;
        }
    }

    private function addComplexGeometry(
        array &$element, 
        array $objElement, 
        array $properties, 
        bool $layerStyle, 
        array $dataLayer, 
        ?array $objLocstyle
    ): void {
        $element['cluster'] = false;
        $element['excludeFromSingleLayer'] = '1';
        
        $geojson = $objElement['geojson'];
        if (strpos($geojson, 'FeatureCollection') === false) {
             $geojson = '{"type": "FeatureCollection", "features": ' . $geojson . '}';
        }
            
        $data = json_decode($geojson, true);
        
        if (isset($data['features'])) {
            foreach ($data['features'] as $key => $feature) {
                $data['features'][$key]['properties']['zindex'] = -5;
                $data['features'][$key]['properties'] = array_merge($data['features'][$key]['properties'] ?? [], $properties);
            }
        }
        
        $data['properties'] = $properties;
        
        $element['content'] = [[
            'id' => $element['id'],
            'type' => 'GeoJSON',
            'locationStyle' => $layerStyle ? $dataLayer['locstyle'] : ($objLocstyle['locstyle'] ?? null),
            'data' => $data,
            'position' => ['positionId' => $element['id']],
            'format' => 'GeoJSON',
        ]];
    }

    private function addSimpleGeometry(
        array &$element, 
        array $objElement, 
        array $properties, 
        bool $layerStyle, 
        array $dataLayer, 
        ?array $objLocstyle
    ): void {
        $data = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$objElement['geox'], $objElement['geoy']],
            ],
            'properties' => $properties,
        ];

        $element['content'] = [[
            'id' => $element['id'],
            'type' => 'GeoJSON',
            'locationStyle' => $layerStyle ? $dataLayer['locstyle'] : ($objLocstyle['locstyle'] ?? null),
            'data' => $data,
            'position' => ['positionId' => $element['id']],
            'format' => 'GeoJSON',
        ]];
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
            'SELECT * FROM tl_gutesio_data_element WHERE alias = ?'
        )->execute($alias)->fetchAssoc() ?: [];
    }

    private function getElementType(string $elementId): ?array
    {
        $typeIds = $this->cache['elementTypes'][$elementId] ?? [];
        if (empty($typeIds)) {
            return $this->database->prepare(
                'SELECT type.* FROM tl_gutesio_data_type AS type 
                 INNER JOIN tl_gutesio_data_element_type AS typeElem 
                 ON typeElem.typeId = type.uuid
                 WHERE typeElem.elementId = ? ORDER BY typeElem.rank ASC LIMIT 1'
            )->execute($elementId)->fetchAssoc() ?: [];
        }

        $typeId = $typeIds[0];
        return $this->cache['types'][$typeId] ?? [];
    }

    private function loadChildElements(array $elem, array $type, array $dataLayer): array
    {
        if (!$elem['showcaseIds'] || !$type['showLinkedElements']) {
            return [];
        }

        $childElements = [];
        $showcaseIds = array_unique(StringUtil::deserialize($elem['showcaseIds'], true));

        foreach ($showcaseIds as $showcaseId) {
            $childElem = $this->getElement($showcaseId);
            if ($childElem) {
                $childElements[] = $this->createElement($childElem, $dataLayer, $elem, [], false, false, [], true, false);
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
        
        $locstyle = $this->database->prepare(
            'SELECT areaLocstyle as locci FROM tl_c4g_maps WHERE id=?'
        )->execute($layer['id'])->fetchAssoc()['locci'];
        
        if (!$zipElem || !$zipElem['zip']) {
            return null;
        }
        
        $arrPostalCodes = explode(',', $zipElem['zip']);
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

        return [
            "content" => [[
                "data" => json_decode($response),
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