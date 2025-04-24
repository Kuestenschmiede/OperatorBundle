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

/**
 * Class LoadLayersListener
 * Handles loading and processing of map layers for the gutesio operator bundle
 */
class LoadLayersListener
{
    private LayerService $layerService;
    private Database $Database;

    /** @var array<string,array> Cached type data */
    private array $typeMap = [];

    /** @var array<string,array> Cached location style data */
    private array $locStyleMap = [];

    /** @var array<string,array> Cached tag data */
    private array $tagMap = [];

    /** @var array<string,array> Cached element data */
    private array $elementMap = [];

    /** @var string SQL condition for published elements */
    private string $strPublished;

    public function __construct(LayerService $layerService)
    {
        $this->layerService = $layerService;
        $this->Database = Database::getInstance();
        $this->strPublished = ' AND ({{table}}.publishFrom IS NULL OR {{table}}.publishFrom < ' . time() . ') AND ({{table}}.publishUntil IS NULL OR {{table}}.publishUntil > ' . time() . ')';
    }

    /**
     * Handles loading of individual elements for the map
     */
    public function onLoadLayersLoadElement(
        LoadLayersEvent $event,
        string $eventName,
        EventDispatcherInterface $eventDispatcher
    ): void {
        $dataLayer = $event->getLayerData();
        if (!($dataLayer['type'] === 'gutesElem')) {
            return;
        }
        $strPublishedElem = str_replace('{{table}}', 'elem', $this->strPublished);
        $strQueryElem = 'SELECT elem.* FROM tl_gutesio_data_element AS elem WHERE elem.alias =?' . $strPublishedElem;

        $alias = false;
        if (!$alias && isset($_SERVER['HTTP_REFERER'])) {
            $alias = $_SERVER['HTTP_REFERER'];
            $strC = substr_count($alias, '/');
            $arrUrl = explode('/', $alias);

            if (strpos($arrUrl[$strC], '.html')) {
                $alias = substr($arrUrl[$strC], 0, strpos($arrUrl[$strC], '.html'));
            } else {
                $alias = $arrUrl[$strC];
            }
            if (strpos($alias, '?')) {
                $alias = explode('?', $alias)[0];
            }
        }

        if (!$alias) {
            return;
        }

        $event->setPreventCaching(true);
        if (C4GUtils::isValidGUID($alias)) {
            $offerConnections = $this->Database->prepare('SELECT elementId FROM tl_gutesio_data_child_connection WHERE childId = ?')
                ->execute('{' . strtoupper($alias) . '}')->fetchAllAssoc();
            if ($offerConnections && (count($offerConnections) > 0)) {
                $firstConnection = $offerConnections[0];
                $elem = $this->Database->prepare('SELECT * FROM tl_gutesio_data_element WHERE `uuid` = ?')
                    ->execute($firstConnection['elementId'])->fetchAssoc();
            }
        } else {
            $elem = $this->Database->prepare($strQueryElem)->execute($alias)->fetchAssoc();
        }

        if (!$elem) {
            return;
        }

        $childElements = [];
        $strQueryType = 'SELECT type.* FROM tl_gutesio_data_type AS type 
                        INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.typeId = type.uuid
                        WHERE typeElem.elementId =?';
        $type = $this->Database->prepare($strQueryType)->execute($elem['uuid'])->fetchAssoc();

        if ($elem['showcaseIds'] && $type['showLinkedElements']) {
            $showcaseIds = StringUtil::deserialize($elem['showcaseIds'], true);
            foreach ($showcaseIds as $showcaseId) {
                $strQueryElems = 'SELECT elem.* FROM tl_gutesio_data_element AS elem
                                            WHERE elem.uuid = ?' . $strPublishedElem;
                $childElem = $this->Database->prepare($strQueryElems)->execute($showcaseId)->fetchAssoc();
                if ($childElem) {
                    $childElements[] = $this->createElement($childElem, $dataLayer, $elem, [], false);
                }
            }
        }

        if ($childElements && (count($childElements) > 1) && $type['showLinkedElements']) {
            $dataLayer['childs'] = $childElements;
        } else {
            $elements = [];
            $elements[] = $this->createElement($elem, $dataLayer, $type, $childElements, false, true);
            $dataLayer['childs'] = $elements;
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
    ) {
        $dataLayer = $event->getLayerData();
        if (!($dataLayer['type'] === 'gutesPart')) {
            return;
        }
        $objDataLayer = C4gMapsModel::findByPk($dataLayer['id']);
        if (!$objDataLayer) {
            return;
        }
        $configuredTypes = unserialize($objDataLayer->types);
        $objTypes = [];
        if ($configuredTypes) {
            foreach($configuredTypes as $type) {
                $tempType = GutesioDataTypeModel::findOneBy('uuid', $type);
                if ($tempType) {
                    $objTypes[] = $tempType;
                }
            }
        }
        else {
            $arrOptions = [
                'order' => "tl_gutesio_data_type.name ASC"
            ];
            $objTypes = GutesioDataTypeModel::findAll($arrOptions);
        }
        $types = [];
        $sameElements = [];
        $skipElements = unserialize($objDataLayer->skipElements);

        foreach ($objTypes as $objType) {
            $type = $objType->row();
            $strPublishedElem = str_replace('{{table}}', 'elem', $this->strPublished);
            $strQueryElems = 'SELECT elem.* FROM tl_gutesio_data_element AS elem
                INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.elementId = elem.uuid
                WHERE typeElem.typeId = ?' . $strPublishedElem .
                ' ORDER BY elem.name ASC';
            $arrElems = $this->Database->prepare($strQueryElems)->execute($type['uuid'])->fetchAllAssoc();
            $elements = [];
            $checkDuplicates = [];
            foreach ($arrElems as $elem) {
                foreach ($skipElements as $skipElem) {
                    if ($skipElem === $elem['uuid']) {
                        continue 2;
                    }
                }

                $childElements = [];
                $sameElements[$elem['uuid']][] = $elem;
                if ($elem['showcaseIds'] && $type['showLinkedElements']) {
                    $showcaseIds = StringUtil::deserialize($elem['showcaseIds'], true);
                    foreach ($showcaseIds as $showcaseId) {
                        $strQueryElems = 'SELECT elem.* FROM tl_gutesio_data_element AS elem
                                                WHERE elem.uuid = ?' . $strPublishedElem;
                        $childElem = $this->Database->prepare($strQueryElems)->execute($showcaseId)->fetchAssoc();
                        if ($childElem) {
                            $childElements[] = $this->createElement($childElem, $dataLayer, $elem, [], true, false, $sameElements[$elem['uuid']]);
                        }
                    }
                }
                $doCreateElement = true;
                if (key_exists($elem['uuid'],$checkDuplicates)) {
                    foreach ($checkDuplicates[$elem['uuid']] as $checkType) {
                        if ($checkType === $type['uuid']) {
                            $doCreateElement = false;
                            break;
                        }
                    }
                }
                if ($doCreateElement) {
                    $elements[] = $this->createElement($elem, $dataLayer, $type, $childElements, true, false, $sameElements[$elem['uuid']]);
                }
                $checkDuplicates[$elem['uuid']][] = $type['uuid'];

            }
            $hideInStarboard = $objDataLayer->skipTypes || count($elements) === 0;
            $singleType = [
                'pid' => $objDataLayer->id,
                'id' => $type['uuid'],
                'name' => $type['name'],
                'hideInStarboard' => $hideInStarboard,
                'childs' => $elements,
                'zoomTo' => true,
            ];
            if ($elements) {
                $types[] = array_merge($dataLayer, $singleType);
            }
        }
        $dataLayer['childs'] = $types;
        $event->setLayerData($dataLayer);
    }

    public function onLoadLayersLoadDirectories(
        LoadLayersEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher
    ) {
        $dataLayer = $event->getLayerData();
        if (!($dataLayer['type'] === 'gutes')) {
            return;
        }
        $objDataLayer = C4gMapsModel::findByPk($dataLayer['id']);
        if (!$objDataLayer) {
            return;
        }

        if ($objDataLayer->popup_share_button) {
            $shareMethods = StringUtil::deserialize($objDataLayer->popup_share_type, true);
            $shareDest = $objDataLayer->popup_share_destination;

            switch ($shareDest) {
                case "con4gis_map":
                case "con4gis_routing":
                    $shareBaseUrl = "";
                    break;
                case "con4gis_map_external":
                case "con4gis_routing_external":
                case "osm":
                case "osm_routing":
                    $shareBaseUrl = $objDataLayer->popup_share_external_link;
                    break;
                case "google_map":
                    $shareBaseUrl = "https://www.google.com/maps/dir/";
                    break;
                case "google_map_routing":
                    $shareBaseUrl = "https://www.google.com/maps/dir/";
                    break;
                default:
                    $shareBaseUrl = "";
            }

            $popupShare = [
                'methods' => $shareMethods,
                'baseUrl' => $shareBaseUrl,
                'destType' => $shareDest,
                'additionalMessage' => $objDataLayer->popup_share_message
            ];
            $dataLayer['popup_share'] = $popupShare;
        }

        $t = 'tl_gutesio_data_directory';
        $arrOptions = [
            'order' => "$t.name ASC",
        ];
        $configuredDirectories = StringUtil::deserialize($objDataLayer->directories, true);
        $configuredTypes = $objDataLayer->types;
        if ($configuredDirectories) {
            $objDirectories = [];
            foreach ($configuredDirectories as $configuredDirectory) {
                $tempDirs = GutesioDataDirectoryModel::findOneBy('uuid', $configuredDirectory);
                if ($tempDirs) {
                    $objDirectories[] = $tempDirs;
                }
            }
        } else {
            $objDirectories = GutesioDataDirectoryModel::findAll($arrOptions);
        }
        $directories = [];
        $sameElements = [];
        $skipElements = StringUtil::deserialize($objDataLayer->skipElements, true);
        $directoryElems = [];
        $typeElems = [];

        $this->loadTags();

        foreach ($objDirectories as $directory) {
            $elemQuery = 'SELECT elem.*, type.uuid AS typeId, type.name AS typeName, dirType.directoryId AS directoryId FROM tl_gutesio_data_type AS type
                INNER JOIN tl_gutesio_data_directory_type AS dirType ON dirType.typeId = type.uuid
                INNER JOIN tl_gutesio_data_element_type AS elemType ON dirType.typeId = elemType.typeId
                INNER JOIN tl_gutesio_data_element AS elem ON elemType.elementId = elem.uuid
                WHERE dirType.directoryId = ?
                ORDER BY type.name ASC';
            $arrElems = $this->Database->prepare($elemQuery)->execute($directory->uuid)->fetchAllAssoc();
            $validTypes = [];

            foreach ($arrElems as $elem) {
                if (in_array($elem['uuid'], $skipElements)) {
                    continue;
                }

                if (!key_exists($directory->uuid.$elem['typeId'], $typeElems)) {
                    $hideInStarboard = (bool)$objDataLayer->skipTypes;
                    $singleType = [
                        'pid' => $directory->uuid,
                        'id' => $elem['typeId'],
                        'name' => $elem['typeName'],
                        'hideInStarboard' => $hideInStarboard,
                        'childs' => [],
                        'zoomTo' => true,
                    ];
                    $typeElems[$directory->uuid.$elem['typeId']] = array_merge($dataLayer, $singleType);
                    $validTypes[$directory->uuid.$elem['typeId']] = array_merge($dataLayer, $singleType);
                }

                $treeElement = $this->createElement($elem, $dataLayer, ['uuid' => $elem['typeId']]);
                $typeElems[$directory->uuid.$elem['typeId']]['childs'][] = $treeElement;
                $validTypes[$directory->uuid.$elem['typeId']]['childs'][] = $treeElement;
            }

            if (!key_exists($directory->uuid, $directoryElems)) {
                $singleDir = [
                    'pid' => $dataLayer['id'],
                    'id' => $directory->uuid,
                    'name' => $directory->name,
                    'hideInStarboard' => count($validTypes) === 0,
                    'childs' => array_values($validTypes),
                ];
                $directoryElems[$directory->uuid][] = $singleDir;
                $directories[] = array_merge($dataLayer, $singleDir);
            }
        }
        $dataLayer['childs'] = $directories;
        $event->setLayerData($dataLayer);
    }

    private function loadTags(): void
    {
        if (!empty($this->tagMap)) {
            return;
        }

        $sql = "SELECT uuid, imageCDN, name FROM tl_gutesio_data_tag";
        $tagResult = $this->Database->prepare($sql)->execute()->fetchAllAssoc();

        foreach ($tagResult as $tag) {
            $this->tagMap[$tag['uuid']] = $tag;
        }
    }

    private function createElement($objElement, $dataLayer, $parent, $childElements = [], $withPopup = true, $layerStyle = false, $sameElements = []): array
    {
        if (!key_exists($objElement['uuid'], $this->locStyleMap)) {
            $strQueryLocstyle = 'SELECT type.locstyle, type.loctype FROM tl_gutesio_data_type AS type
                                        INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.typeId = type.uuid
                                        WHERE typeElem.elementId = ? ORDER BY typeElem.rank ASC LIMIT 1';
            $objLocstyle = $this->Database->prepare($strQueryLocstyle)->execute($objElement['uuid'])->fetchAssoc();
            $this->locStyleMap[$objElement['uuid']] = $objLocstyle;
        } else {
            $objLocstyle = $this->locStyleMap[$objElement['uuid']];
        }

        $tagQuery = "SELECT tagId FROM tl_gutesio_data_tag_element WHERE `elementId` = ?";
        $elementTags = $this->Database->prepare($tagQuery)->execute($objElement['uuid'])->fetchAllAssoc();
        $tags = [];
        $tagUuids = [];
        foreach ($elementTags as $key => $elementTag) {
            $tag = $this->tagMap[$elementTag['tagId']];

            if ($tag['name'] && !key_exists($tag['uuid'], $tagUuids)) {
                $tags[$key] = $tag['name'];
                $tagUuids[$tag['uuid']] = true;
            }
        }

        $tagUuids[$parent['uuid']] = true;
        $popup = [];
        if ($withPopup) {
            $popup = [
                'async' => true,
                'content' => 'showcase::' . $objElement['uuid'],
                'routing_link' => true,
            ];
        }

        $link = count($sameElements) > 1;

        $element = [
            'pid' => $parent['uuid'],
            'id' => $objElement['uuid'],
            'key' => $objElement['uuid'].$parent['uuid'],
            'type' => 'GeoJSON',
            'tags' => $tags,
            'childs' => $childElements,
            'name' => html_entity_decode($objElement['name']),
            'zIndex' => 2000,
            'layername' => html_entity_decode($objElement['name']),
            'locstyle' => $layerStyle ? $dataLayer['locstyle'] : $objLocstyle['locstyle'],
            'hideInStarboard' => false,
            'zoomTo' => true,
        ];

        if ($dataLayer['popup_share']) {
            $element['popup_share'] = $dataLayer['popup_share'];
        }

        if (($objElement['geox'] && $objElement['geoy']) || $objElement['geojson']) {
            $properties = array_merge([
                'projection' => 'EPSG:4326',
                'opening_hours' => $objElement['opening_hours'],
                'phoneHours' => $objElement['phoneHours'],
                'popup' => $popup,
                'graphicTitle' => $objElement['name'],
            ], $tagUuids);
            $data = [];
            if ($objLocstyle['loctype'] === 'Editor' || $objLocstyle['loctype'] === 'LineString' || $objLocstyle['loctype'] === 'Polygon') {
                $element['cluster'] = false;
                $element['excludeFromSingleLayer'] = true;
                $geojson = strpos($objElement['geojson'], 'FeatureCollection') ? $objElement['geojson'] : '{"type": "FeatureCollection", "features": ' . $objElement['geojson'] . '}';
                $data = json_decode($geojson, true);
                foreach ($data['features'] as $key => $feature) {
                    $data['features'][$key]['properties']['zindex'] = -5;
                }
                $data['properties'] = $properties;
            } else {
                $data = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [
                            $objElement['geox'], $objElement['geoy'],
                        ],
                    ],
                    'properties' => $properties,
                ];
            }
            $content = [
                [
                    'id' => $objElement['uuid'],
                    'type' => 'GeoJSON',
                    'locationStyle' => $layerStyle ?  $dataLayer['locstyle'] : $objLocstyle['locstyle'],
                    'data' => $data,
                    'position' => [
                        'positionId' => $objElement['uuid'],
                    ],
                    'format' => 'GeoJSON',
                ],
            ];

            $element['content'] = $content;
        }
        if ($popup) {
            $element['popup'] = $popup;
        }
        $element = array_merge($dataLayer, $element);

        return $element;
    }

    private function addArea($objElement, $layer): ?array
    {
        $settings = C4gMapSettingsModel::findOnly();
        if (!$settings) {
            return null;
        }
        
        $url = $settings->con4gisIoUrl;
        $key = $settings->con4gisIoKey;
        $strSurroundingPostals = 'SELECT typeFieldValue as zip FROM tl_gutesio_data_type_element_values
                                                WHERE elementId = ? AND typeFieldKey="surrZip"';
        $zipElem = $this->Database->prepare($strSurroundingPostals)->execute($objElement['uuid'])->fetchAssoc();
        $strLocstyle = 'SELECT areaLocstyle as locci FROM tl_c4g_maps WHERE id=?';
        $locstyle = $this->Database->prepare($strLocstyle)->execute($layer['id'])->fetchAssoc()['locci'];
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