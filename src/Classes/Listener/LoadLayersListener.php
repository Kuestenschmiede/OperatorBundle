<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Listener;

//use con4gis\DataBundle\Classes\Event\LoadPropertiesEvent;
//use con4gis\DataBundle\Classes\Popup\Popup;
use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\MapsBundle\Resources\contao\models\C4gMapSettingsModel;
use Contao\Request;
use Contao\System;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataDirectoryModel;
use con4gis\MapsBundle\Classes\Events\LoadLayersEvent;
use con4gis\MapsBundle\Classes\Services\LayerService;
use con4gis\MapsBundle\Resources\contao\models\C4gMapsModel;
use Contao\Database;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataTypeModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LoadLayersListener
{
    private $layerService;

    private $Database;

    /**
     * LayerContentService constructor.
     * @param LayerService $layerService
     */
    public function __construct(LayerService $layerService)
    {
        $this->layerService = $layerService;
        $this->Database = Database::getInstance();
        $this->strPublished = ' AND (NOT {{table}}.releaseType = "external") AND ({{table}}.publishFrom IS NULL OR {{table}}.publishFrom < ' . time() . ') AND ({{table}}.publishUntil IS NULL OR {{table}}.publishUntil > ' . time() . ')';
    }

    /**
     * Handles the loading of layers and loads elements based on the event data.
     *
     * @param LoadLayersEvent $event The event that triggered the layer loading.
     * @param string $eventName The name of the event.
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher interface.
     *
     * @return void
     */
    public function onLoadLayersLoadElement(
        LoadLayersEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher)
    {
        $dataLayer = $event->getLayerData();
        if (!($dataLayer['type'] === 'gutesElem')) {
            return;
        }
        $strPublishedElem = str_replace('{{table}}', 'elem', $this->strPublished);
        $strQueryElem = 'SELECT elem.* FROM tl_gutesio_data_element AS elem WHERE elem.alias =?' . $strPublishedElem;

        //This does not work in the offer detail view
        //$alias = System::getContainer()->get('request_stack')->getSession()->get('gutesio_element_alias', '');

        $alias = false;
        if (!$alias) {
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
        $event->setPreventCaching(true);
        if (C4GUtils::isValidGUID($alias)) {
            $offerConnections = Database::getInstance()->prepare('SELECT elementId FROM tl_gutesio_data_child_connection WHERE childId = ?')
                ->execute('{' . strtoupper($alias) . '}')->fetchAllAssoc();
            if ($offerConnections and (count($offerConnections) > 0)) {
                $firstConnection = $offerConnections[0];
                $elem = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_element WHERE `uuid` = ?')
                    ->execute($firstConnection['elementId'])->fetchAssoc();
            }
        } else {
            $elem = $this->Database->prepare($strQueryElem)->execute($alias)->fetchAssoc();
        }

        $childElements = [];
        if ($elem) {
            $strQueryType = 'SELECT type.* FROM tl_gutesio_data_type AS type 
                            INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.typeId = type.uuid
                            WHERE typeElem.elementId =?';
            $type = $this->Database->prepare($strQueryType)->execute($elem['uuid'])->fetchAssoc();

            if ($elem['showcaseIds'] && $type['showLinkedElements']) {
                foreach (unserialize($elem['showcaseIds']) as $showcaseId) {
                    $strQueryElems = 'SELECT elem.* FROM tl_gutesio_data_element AS elem
                                                WHERE elem.uuid = ?' . $strPublishedElem;
                    $childElem = $this->Database->prepare($strQueryElems)->execute($showcaseId)->fetchAssoc();
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
        $dataLayer['childs'][] = $this->addArea($elem, $dataLayer);
        $event->setLayerData($dataLayer);
    }

    /**
     * Handles the LoadLayersEvent and processes layer data based on specific conditions.
     *
     * @param LoadLayersEvent $event The event instance containing layer data.
     * @param string $eventName The name of the event.
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher interface.
     */
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
                    foreach (unserialize($elem['showcaseIds']) as $showcaseId) {
                        $strQueryElems = 'SELECT elem.* FROM tl_gutesio_data_element AS elem
                                                WHERE elem.uuid = ?' . $strPublishedElem;
                        $childElem = $this->Database->prepare($strQueryElems)->execute($showcaseId)->fetchAssoc();
                        $childElements[] = $this->createElement($childElem, $dataLayer, $elem, [], true, false, $sameElements[$elem['uuid']]);
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

    /**
     * Handles the loading of layer directories during a LoadLayersEvent.
     *
     * @param LoadLayersEvent $event The event triggering the load action.
     * @param string $eventName The name of the event.
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher interface.
     */
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

        $t = 'tl_gutesio_data_directory';
        $arrOptions = [
            'order' => "$t.name ASC",
        ];
        $configuredDirectories = unserialize($objDataLayer->directories);
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
        $skipElements = unserialize($objDataLayer->skipElements);
        foreach ($objDirectories as $directory) {
            $strQueryTypes = 'SELECT type.* FROM tl_gutesio_data_type AS type
                INNER JOIN tl_gutesio_data_directory_type AS dirType ON dirType.typeId = type.uuid
                WHERE dirType.directoryId = ?
                ORDER BY type.name ASC';
            $arrTypes = $this->Database->prepare($strQueryTypes)->execute($directory->uuid)->fetchAllAssoc();
            $types = [];
            foreach ($arrTypes as $type) {
                if ($configuredTypes && !strpos($configuredTypes, $type['uuid'])) {
                    continue;
                }
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
                        foreach (unserialize($elem['showcaseIds']) as $showcaseId) {
                            $strQueryElems = 'SELECT elem.* FROM tl_gutesio_data_element AS elem
                                                WHERE elem.uuid = ?' . $strPublishedElem;
                            $childElem = $this->Database->prepare($strQueryElems)->execute($showcaseId)->fetchAssoc();
                            $childElements[] = $this->createElement($childElem, $dataLayer, $elem, [], true, false, $sameElements[$elem['uuid']]);
                        }
                    }
                    $doCreateElement = true;
                    if ($checkDuplicates[$elem['uuid']]) {
                        foreach ($checkDuplicates[$elem['uuid']] as $checkType) {
                            if ($checkType['type'] === $type['uuid'] && $checkType['directory'] === $directory->uuid) {
                                $doCreateElement = false;
                                break;
                            }
                        }
                    }
                    if ($doCreateElement) {
                        $elements[] = $this->createElement($elem, $dataLayer, $type, $childElements, true, false, $sameElements[$elem['uuid']]);
                    }
                    $checkDuplicates[$elem['uuid']][] = ['type'=>$type['uuid'],'directory'=>$directory->uuid];
                }
                $hideInStarboard = $objDataLayer->skipTypes || count($elements) === 0;
                $singleType = [
                    'pid' => $directory->uuid,
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
            if (count($types) < 1) {
                continue;
            }
            else if (count($types) === 1 && $configuredTypes) {
                $singleDir = $types[0];
            }
            else {
                $singleDir = [
                    'pid' => $dataLayer['id'],
                    'id' => $directory->uuid,
                    'name' => $directory->name,
                    'hideInStarboard' => count($types) === 0,
                    'childs' => $types,
                ];
            }
            if ($types) {
                $directories[] = array_merge($dataLayer, $singleDir);
            }
        }
        //ToDO doesn't work with default settings
        //$directories = array_unique($directories);
        $dataLayer['childs'] = $directories;
        $event->setLayerData($dataLayer);
    }

    /**
     * Creates an element for the data layer based on the given parameters.
     *
     * @param array $objElement The object element data.
     * @param array $dataLayer The data layer details.
     * @param array $parent The parent element data.
     * @param array $childElements The child elements associated with the parent element.
     * @param bool $withPopup Flag indicating whether to include popup information.
     * @param bool $layerStyle Flag indicating whether to use the layer style from dataLayer.
     * @param array $sameElements List of elements that are the same.
     *
     * @return array The constructed element with its properties.
     */
    private function createElement($objElement, $dataLayer, $parent, $childElements = [], $withPopup = true, $layerStyle = false, $sameElements = [])
    {
        $strQueryLocstyle = 'SELECT type.locstyle, type.loctype FROM tl_gutesio_data_type AS type 
                                        INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.typeId = type.uuid
                                        WHERE typeElem.elementId = ? ORDER BY typeElem.rank ASC LIMIT 1';'//AND typeElem.rank = 0';
        $objLocstyle = $this->Database->prepare($strQueryLocstyle)->execute($objElement['uuid'])->fetchAssoc();
        $strQueryTags = 'SELECT tag.uuid, tag.imageCDN, tag.name FROM tl_gutesio_data_tag AS tag
                                        INNER JOIN tl_gutesio_data_tag_element AS elementTag ON elementTag.tagId = tag.uuid
                                        WHERE tag.published = 1 AND elementTag.elementId = ? ORDER BY tag.name ASC';
        $arrTags = $this->Database->prepare($strQueryTags)->execute($objElement['uuid'])->fetchAllAssoc();
        $tags = [];
        $tagUuids = [];
        foreach ($arrTags as $key => $tag) {
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

    /**
     * Adds an area to the map layer based on surrounding postal codes and location style from database.
     *
     * Retrieves surrounding postal codes associated with the given element and constructs an Overpass API query
     * to fetch geospatial data. Sends the query to the Overpass API and processes the response to generate
     * a map area configuration.
     *
     * @param array $objElement The element object containing necessary identifiers.
     * @param array $layer The map layer configuration.
     *
     * @return array|bool Returns the map area configuration or false if no surrounding postal codes are found.
     */
    private function addArea ($objElement, $layer) {
        $settings = C4gMapSettingsModel::findOnly();
        $url = $settings->con4gisIoUrl;
//        $url = "https://osm.kartenkueste.de/api/interpreter";
        $key = $settings->con4gisIoKey;
        $strSurroundingPostals = 'SELECT typeFieldValue as zip FROM tl_gutesio_data_type_element_values
                                                WHERE elementId = ? AND typeFieldKey="surrZip"';
        $zipElem = $this->Database->prepare($strSurroundingPostals)->execute($objElement['uuid'])->fetchAssoc();
        $strLocstyle = 'SELECT areaLocstyle as locci FROM tl_c4g_maps WHERE id=?';
        $locstyle = $this->Database->prepare($strLocstyle)->execute($layer['id'])->fetchAssoc()['locci'];
        if (!$zipElem && !$zipElem['zip']) {
            return false;
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
        if ($_SERVER['HTTP_REFERER']) {
            $REQUEST->setHeader('Referer', $_SERVER['HTTP_REFERER']);
        }
        if ($_SERVER['HTTP_USER_AGENT']) {
            $REQUEST->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT']);
        }
//        $sendUrl = $url . "?key=" . $key . "&data=" . urlencode($strOvp);
        $sendUrl = $url . "osm.php?key=" . $key . "&data=" . rawurlencode($strOvp);
        $REQUEST->send($sendUrl);
        $response = $REQUEST->response;
        $return = ["content" => [[
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
        return $return;
    }
}
