<?php

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

        // Load element types
        $elementTypesResult = $this->database->execute(
            "SELECT elementId, typeId FROM tl_gutesio_data_element_type"
        )->fetchAllAssoc();
        foreach ($elementTypesResult as $elementType) {
            $this->cache['elementTypes'][$elementType['elementId']][] = $elementType['typeId'];
        }
    }

    private function getElement(string $uuid): ?array
    {
        if (!isset($this->cache['elements'][$uuid])) {
            $strPublishedElem = str_replace('{{table}}', 'elem', $this->publishedCondition);
            $result = $this->database->prepare(
                "SELECT * FROM tl_gutesio_data_element WHERE uuid = ?" . $strPublishedElem
            )->execute($uuid)->fetchAssoc();
            
            $this->cache['elements'][$uuid] = $result ?: null;
        }
        
        return $this->cache['elements'][$uuid];
    }

    private function getLocationStyle(string $elementId): ?array
    {
        if (!isset($this->cache['locStyles'][$elementId])) {
            $result = $this->database->prepare(
                'SELECT type.locstyle, type.loctype 
                 FROM tl_gutesio_data_type AS type
                 INNER JOIN tl_gutesio_data_element_type AS typeElem 
                 ON typeElem.typeId = type.uuid
                 WHERE typeElem.elementId = ? 
                 ORDER BY typeElem.rank ASC 
                 LIMIT 1'
            )->execute($elementId)->fetchAssoc();
            
            $this->cache['locStyles'][$elementId] = $result ?: null;
        }
        
        return $this->cache['locStyles'][$elementId];
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
        $type = $this->getElementType($elem['uuid']);
        $childElements = $this->loadChildElements($elem, $type, $dataLayer);

        if ($childElements && count($childElements) > 1 && $type['showLinkedElements']) {
            $dataLayer['childs'] = $childElements;
        } else {
            $dataLayer['childs'] = [$this->createElement($elem, $dataLayer, $type, $childElements, false, true)];
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
        $types = $this->loadTypes($objDataLayer);
        $skipElements = StringUtil::deserialize($objDataLayer->skipElements, true);
        $sameElements = [];
        $typeElements = $this->processTypes($types, $dataLayer, $skipElements, $sameElements, $objDataLayer);
        
        $dataLayer['childs'] = array_values($typeElements);
        $event->setLayerData($dataLayer);
    }

    private function loadTypes($objDataLayer): array
    {
        $configuredTypes = unserialize($objDataLayer->types);
        if ($configuredTypes) {
            return array_filter(array_map(function($type) {
                return GutesioDataTypeModel::findOneBy('uuid', $type);
            }, $configuredTypes));
        }

        return GutesioDataTypeModel::findAll([
            'order' => "tl_gutesio_data_type.name ASC"
        ]) ?: [];
    }

    private function processTypes(array $types, array $dataLayer, array $skipElements, array &$sameElements, $objDataLayer): array
    {
        $typeElements = [];
        $strPublishedElem = str_replace('{{table}}', 'elem', $this->publishedCondition);

        foreach ($types as $type) {
            $typeData = $type->row();
            $elements = $this->loadTypeElements($typeData['uuid'], $strPublishedElem, $skipElements);
            
            if (empty($elements)) {
                continue;
            }

            $processedElements = $this->processTypeElements($elements, $dataLayer, $type, $sameElements);
            
            if (!empty($processedElements)) {
                $typeElements[$typeData['uuid']] = array_merge($dataLayer, [
                    'pid' => $objDataLayer->id,
                    'id' => $typeData['uuid'],
                    'name' => $typeData['name'],
                    'hideInStarboard' => $objDataLayer->skipTypes,
                    'childs' => $processedElements,
                    'zoomTo' => true,
                ]);
            }
        }

        return $typeElements;
    }

    private function loadTypeElements(string $typeId, string $publishedCondition, array $skipElements): array
    {
        $query = 'SELECT elem.* FROM tl_gutesio_data_element AS elem
                 INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.elementId = elem.uuid
                 WHERE typeElem.typeId = ?' . $publishedCondition . ' ORDER BY elem.name ASC';
                 
        $elements = $this->database->prepare($query)->execute($typeId)->fetchAllAssoc();
        
        return array_filter($elements, function($elem) use ($skipElements) {
            return !in_array($elem['uuid'], $skipElements);
        });
    }

    private function processTypeElements(array $elements, array $dataLayer, $type, array &$sameElements): array
    {
        $processedElements = [];
        $checkDuplicates = [];

        foreach ($elements as $elem) {
            $sameElements[$elem['uuid']][] = $elem;
            $childElements = $this->loadChildElements($elem, $type->row(), $dataLayer);

            if ($this->shouldCreateElement($elem['uuid'], $type->uuid, $checkDuplicates)) {
                $processedElements[] = $this->createElement(
                    $elem, 
                    $dataLayer, 
                    $type->row(), 
                    $childElements, 
                    true, 
                    false, 
                    $sameElements[$elem['uuid']]
                );
            }
            
            $checkDuplicates[$elem['uuid']][] = $type->uuid;
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
        $this->processShareSettings($dataLayer, $objDataLayer);
        
        $directories = $this->loadDirectories($objDataLayer);
        $processedDirectories = $this->processDirectories($directories, $dataLayer, $objDataLayer);
        
        $dataLayer['childs'] = $processedDirectories;
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
        $directoryElements = [];
        $typeElements = [];
        $skipElements = StringUtil::deserialize($objDataLayer->skipElements, true);

        foreach ($directories as $directory) {
            // Da wir jetzt ein Array haben, müssen wir den Zugriff anpassen
            $directoryUuid = $directory['uuid'] ?? null;
            $directoryName = $directory['name'] ?? '';

            if (!$directoryUuid) {
                continue;
            }

            $elements = $this->loadDirectoryElements($directoryUuid);
            $validTypes = $this->processDirectoryElements(
                $elements,
                $dataLayer,
                $skipElements,
                $typeElements,
                $directoryUuid,
                $objDataLayer
            );

            if (!empty($validTypes)) {
                $singleDir = [
                    'pid' => $dataLayer['id'],
                    'id' => $directoryUuid,
                    'name' => $directoryName,
                    'hideInStarboard' => empty($validTypes),
                    'childs' => array_values($validTypes),
                ];
                $directoryElements[$directoryUuid][] = $singleDir;
                $processedDirectories[] = array_merge($dataLayer, $singleDir);
            }
        }

        return $processedDirectories;
    }

    private function loadDirectoryElements(string $directoryId): array
    {
        $query = 'SELECT elem.*, type.uuid AS typeId, type.name AS typeName, dirType.directoryId AS directoryId 
                 FROM tl_gutesio_data_type AS type
                 INNER JOIN tl_gutesio_data_directory_type AS dirType ON dirType.typeId = type.uuid
                 INNER JOIN tl_gutesio_data_element_type AS elemType ON dirType.typeId = elemType.typeId
                 INNER JOIN tl_gutesio_data_element AS elem ON elemType.elementId = elem.uuid
                 WHERE dirType.directoryId = ?
                 ORDER BY type.name ASC';
                 
        return $this->database->prepare($query)->execute($directoryId)->fetchAllAssoc();
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

        foreach ($elements as $elem) {
            if (in_array($elem['uuid'], $skipElements)) {
                continue;
            }

            $typeKey = $directoryId . $elem['typeId'];
            if (!isset($typeElements[$typeKey])) {
                $hideInStarboard = (bool)$objDataLayer->skipTypes;
                $singleType = [
                    'pid' => $directoryId,
                    'id' => $elem['typeId'],
                    'name' => $elem['typeName'],
                    'hideInStarboard' => $hideInStarboard,
                    'childs' => [],
                    'zoomTo' => true,
                ];
                $typeElements[$typeKey] = array_merge($dataLayer, $singleType);
                $validTypes[$typeKey] = array_merge($dataLayer, $singleType);
            }

            $treeElement = $this->createElement($elem, $dataLayer, ['uuid' => $elem['typeId']]);
            $typeElements[$typeKey]['childs'][] = $treeElement;
            $validTypes[$typeKey]['childs'][] = $treeElement;
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
        array $sameElements = []
    ): array {
        $objLocstyle = $this->getLocationStyle($objElement['uuid']);
        $tags = $this->getElementTags($objElement['uuid']);
        $tagUuids = array_merge(
            array_combine(array_keys($tags), array_fill(0, count($tags), true)),
            [$parent['uuid'] => true]
        );

        $popup = $withPopup ? [
            'async' => true,
            'content' => 'showcase::' . $objElement['uuid'],
            'routing_link' => true,
        ] : [];

        $element = $this->buildBaseElement($objElement, $parent, $dataLayer, $layerStyle, $objLocstyle, $tags, $childElements);

        if ($dataLayer['popup_share']) {
            $element['popup_share'] = $dataLayer['popup_share'];
        }

        if (($objElement['geox'] && $objElement['geoy']) || $objElement['geojson']) {
            $this->addGeometryData($element, $objElement, $objLocstyle, $popup, $tagUuids, $layerStyle, $dataLayer);
        }

        if ($popup) {
            $element['popup'] = $popup;
        }

        return array_merge($dataLayer, $element);
    }

    private function getElementTags(string $elementId): array
    {
        $elementTags = $this->database->prepare(
            "SELECT tagId FROM tl_gutesio_data_tag_element WHERE elementId = ?"
        )->execute($elementId)->fetchAllAssoc();

        $tags = [];
        $tagUuids = [];
        foreach ($elementTags as $key => $elementTag) {
            $tag = $this->cache['tags'][$elementTag['tagId']] ?? null;
            if ($tag && $tag['name'] && !isset($tagUuids[$tag['uuid']])) {
                $tags[$key] = $tag['name'];
                $tagUuids[$tag['uuid']] = true;
            }
        }

        return $tags;
    }

    private function buildBaseElement(
        array $objElement, 
        array $parent, 
        array $dataLayer, 
        bool $layerStyle, 
        ?array $objLocstyle, 
        array $tags, 
        array $childElements
    ): array {
        return [
            'pid' => $parent['uuid'],
            'id' => $objElement['uuid'],
            'key' => $objElement['uuid'] . $parent['uuid'],
            'type' => 'GeoJSON',
            'tags' => $tags,
            'childs' => $childElements,
            'name' => html_entity_decode($objElement['name']),
            'zIndex' => 2000,
            'layername' => html_entity_decode($objElement['name']),
            'locstyle' => $layerStyle ? $dataLayer['locstyle'] : ($objLocstyle['locstyle'] ?? null),
            'hideInStarboard' => false,
            'zoomTo' => true,
        ];
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
            'opening_hours' => $objElement['opening_hours'],
            'phoneHours' => $objElement['phoneHours'],
            'popup' => $popup,
            'graphicTitle' => $objElement['name'],
        ];
        $properties = array_merge($properties, $tagUuids);

        if ($objLocstyle && in_array($objLocstyle['loctype'], ['Editor', 'LineString', 'Polygon'])) {
            $this->addComplexGeometry($element, $objElement, $properties, $layerStyle, $dataLayer, $objLocstyle);
        } else {
            $this->addSimpleGeometry($element, $objElement, $properties, $layerStyle, $dataLayer, $objLocstyle);
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
        $element['excludeFromSingleLayer'] = true;
        
        $geojson = strpos($objElement['geojson'], 'FeatureCollection') 
            ? $objElement['geojson'] 
            : '{"type": "FeatureCollection", "features": ' . $objElement['geojson'] . '}';
            
        $data = json_decode($geojson, true);
        
        foreach ($data['features'] as $key => $feature) {
            $data['features'][$key]['properties']['zindex'] = -5;
        }
        
        $data['properties'] = $properties;
        
        $element['content'] = [[
            'id' => $objElement['uuid'],
            'type' => 'GeoJSON',
            'locationStyle' => $layerStyle ? $dataLayer['locstyle'] : ($objLocstyle['locstyle'] ?? null),
            'data' => $data,
            'position' => ['positionId' => $objElement['uuid']],
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
            'id' => $objElement['uuid'],
            'type' => 'GeoJSON',
            'locationStyle' => $layerStyle ? $dataLayer['locstyle'] : ($objLocstyle['locstyle'] ?? null),
            'data' => $data,
            'position' => ['positionId' => $objElement['uuid']],
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
        }

        $strPublishedElem = str_replace('{{table}}', 'elem', $this->publishedCondition);
        return $this->database->prepare(
            'SELECT * FROM tl_gutesio_data_element WHERE alias = ?' . $strPublishedElem
        )->execute($alias)->fetchAssoc();
    }

    private function getElementType(string $elementId): ?array
    {
        return $this->database->prepare(
            'SELECT type.* FROM tl_gutesio_data_type AS type 
             INNER JOIN tl_gutesio_data_element_type AS typeElem 
             ON typeElem.typeId = type.uuid
             WHERE typeElem.elementId = ?'
        )->execute($elementId)->fetchAssoc();
    }

    private function loadChildElements(array $elem, array $type, array $dataLayer): array
    {
        if (!$elem['showcaseIds'] || !$type['showLinkedElements']) {
            return [];
        }

        $childElements = [];
        $showcaseIds = StringUtil::deserialize($elem['showcaseIds'], true);

        foreach ($showcaseIds as $showcaseId) {
            $childElem = $this->getElement($showcaseId);
            if ($childElem) {
                $childElements[] = $this->createElement($childElem, $dataLayer, $elem, [], false);
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