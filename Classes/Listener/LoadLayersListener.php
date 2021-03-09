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

//use con4gis\DataBundle\Classes\Event\LoadPropertiesEvent;
//use con4gis\DataBundle\Classes\Popup\Popup;
use con4gis\CoreBundle\Classes\C4GUtils;
use Contao\File;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataDirectoryModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataElementModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataElementTypeModel;
use con4gis\MapsBundle\Classes\Events\LoadLayersEvent;
use con4gis\MapsBundle\Classes\Services\LayerService;
use con4gis\MapsBundle\Resources\contao\models\C4gMapsModel;
use con4gis\ProjectsBundle\Classes\Maps\C4GBrickMapFrontendParent;
use Contao\Controller;
use Contao\Database;
use Contao\StringUtil;
use Contao\FilesModel;
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
        $this->strPublished = " AND ({{table}}.publishFrom IS NULL OR {{table}}.publishFrom < " . time() . ") AND ({{table}}.publishUntil IS NULL OR {{table}}.publishUntil > " . time() . ")";

    }
    public function onLoadLayersLoadElement(
        LoadLayersEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher)
    {
        $dataLayer = $event->getLayerData();
        if (!($dataLayer['type'] === 'gutesElem')) {
            return;
        }
        $strPublishedElem = str_replace("{{table}}", "elem", $this->strPublished);
        $strQueryElem = "SELECT elem.* FROM tl_gutesio_data_element AS elem WHERE elem.alias =?" . $strPublishedElem;
        $alias = $_SERVER['HTTP_REFERER'];
        $strC = substr_count($alias, '/');
        $arrUrl = explode('/', $alias);
        $alias = explode('.', $arrUrl[$strC])[0];

        if (C4GUtils::isValidGUID($alias)) {
            $offerConnections = Database::getInstance()->prepare("SELECT elementId FROM tl_gutesio_data_child_connection WHERE childId = ?")
                ->execute('{'.strtoupper($alias).'}')->fetchAllAssoc();
            if ($offerConnections and (count($offerConnections) > 0)) {
                $firstConnection = $offerConnections[0];
                $elem = Database::getInstance()->prepare("SELECT * FROM tl_gutesio_data_element WHERE `uuid` = ?")
                    ->execute($firstConnection['elementId'])->fetchAssoc();
            }
        } else {
            $elem = $this->Database->prepare($strQueryElem)->execute($alias)->fetchAssoc();
        }

        $childElements = [];
        if ($elem) {
            $strQueryType = "SELECT type.* FROM tl_gutesio_data_type AS type 
                            INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.typeId = type.uuid
                            WHERE typeElem.elementId =?";
            $type = $this->Database->prepare($strQueryType)->execute($elem['uuid'])->fetchAssoc();

            if ($elem['showcaseIds'] && $type['showLinkedElements']) {
                foreach (unserialize($elem['showcaseIds']) as $showcaseId) {
                    $strQueryElems = "SELECT elem.* FROM tl_gutesio_data_element AS elem
                                                WHERE elem.uuid = ?" . $strPublishedElem;
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

        $t = 'tl_gutesio_data_directory';
        $arrOptions = [
            'order' => "$t.name ASC",
        ];
        $configuredDirectories = unserialize($objDataLayer->directories);
        if ($configuredDirectories) {
            $objDirectories = [];
            foreach($configuredDirectories as $configuredDirectory) {
                $objDirectories[] = GutesioDataDirectoryModel::findOneBy("uuid", $configuredDirectory);
            }
        }
        else {
            $objDirectories = GutesioDataDirectoryModel::findAll($arrOptions);
        }
        $directories = [];
        foreach ($objDirectories as $directory) {
            $strQueryTypes = "SELECT type.* FROM tl_gutesio_data_type AS type
                INNER JOIN tl_gutesio_data_directory_type AS dirType ON dirType.typeId = type.uuid
                WHERE dirType.directoryId = ?
                ORDER BY type.name ASC";
            $arrTypes = $this->Database->prepare($strQueryTypes)->execute($directory->uuid)->fetchAllAssoc();
            $types = [];
            foreach ($arrTypes as $type) {
                $strPublishedElem = str_replace("{{table}}", "elem", $this->strPublished);
                $strQueryElems = "SELECT elem.* FROM tl_gutesio_data_element AS elem
                INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.elementId = elem.uuid
                WHERE typeElem.typeId = ?" . $strPublishedElem .
                    " ORDER BY elem.name ASC";
                $arrElems = $this->Database->prepare($strQueryElems)->execute($type['uuid'])->fetchAllAssoc();
                $elements = [];
                foreach ($arrElems as $elem) {
                    $childElements = [];
                    if ($elem['showcaseIds'] && $type['showLinkedElements']) {
                        foreach (unserialize($elem['showcaseIds']) as $showcaseId) {
                            $strQueryElems = "SELECT elem.* FROM tl_gutesio_data_element AS elem
                                                WHERE elem.uuid = ?" . $strPublishedElem;
                            $childElem = $this->Database->prepare($strQueryElems)->execute($showcaseId)->fetchAssoc();
                            $childElements[] = $this->createElement($childElem, $dataLayer, $elem, [], true);
                        }
                    }
                    $elements[] = $this->createElement($elem, $dataLayer, $type, $childElements, true);
                }
                $singleType = [
                    "pid"               => $directory->uuid,
                    "id"                => $type['uuid'],
                    "name"              => $type['name'],
                    "hideInStarboard"   => false,
                    "childs"            => $elements,
                    "addZoomTo"         => true
                ];
                if ($elements) {
                    $types[] = array_merge($dataLayer, $singleType);
                }
            }
            $singleDir = [
                "pid"               => $dataLayer['id'],
                "id"                => $directory->uuid,
                "name"              => $directory->name,
                "hideInStarboard"   => false,
                "childs"            => $types
            ];
            if ($types) {
                $directories[] = array_merge($dataLayer, $singleDir);
            }

        }
        $dataLayer['childs'] = $directories;
        $event->setLayerData($dataLayer);
    }
    private function createElement ($objElement, $dataLayer, $parent, $childElements = [], $withPopup = true, $layerStyle = false) {
        $strQueryLocstyle = "SELECT type.locstyle, type.loctype FROM tl_gutesio_data_type AS type 
                                        INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.typeId = type.uuid
                                        WHERE typeElem.elementId = ? AND typeElem.rank = 0";
        $objLocstyle = $this->Database->prepare($strQueryLocstyle)->execute($objElement['uuid'])->fetchAssoc();
        $strQueryTags = "SELECT tag.uuid, tag.image, tag.name FROM tl_gutesio_data_tag AS tag
                                        INNER JOIN tl_gutesio_data_tag_element AS elementTag ON elementTag.tagId = tag.uuid
                                        WHERE tag.published = 1 AND elementTag.elementId = ? ORDER BY tag.name ASC";
        $arrTags = $this->Database->prepare($strQueryTags)->execute($objElement['uuid'])->fetchAllAssoc();
        $tags = [];
        $tagUuids = [];
        foreach($arrTags as $key => $tag) {
            if ($tag['name']) {
                $tags[$key] = $tag['name'];
                $tagUuids[$tag['uuid']] = true;
            }
        }
        $tagUuids[$parent['uuid']] = true;
        $popup = [];
        if ($withPopup) {
            $popup = [
                "async" => true,
                "content" => "showcase::" . $objElement['uuid'],
                "routing_link" => true
            ];
        }

        $element = [
            "pid"               => $parent['uuid'],
            "id"                => $objElement['uuid'],
            "key"               => $objElement['uuid'],
            "type"              => "GeoJSON",
            "tags"              => $tags,
            "childs"            => $childElements,
            "name"              => $objElement['name'],
            "layername"         => $objElement['name'],
            "locstyle"          => $layerStyle ? $dataLayer['locstyle'] : $objLocstyle['locstyle'],
            "hideInStarboard"   => false,
            "addZoomTo"         => true
        ];
        if (($objElement['geox'] && $objElement['geoy']) || $objElement['geojson']) {
            $properties = array_merge([
                "projection"    => "EPSG:4326",
                "opening_hours" => $objElement['opening_hours'],
                "popup"         => $popup,
                "graphicTitle"  => $objElement['name']
            ], $tagUuids);
            $data = [];
            if ($objLocstyle['loctype'] === "LineString" || $objLocstyle['loctype'] === "Polygon") {
                $element['cluster'] = false;
                $element['excludeFromSingleLayer'] = true;
                $geojson = strpos($objElement['geojson'], "FeatureCollection") ? $objElement['geojson'] : '{"type": "FeatureCollection", "features": ' . $objElement['geojson'] .'}';
                $data = json_decode($geojson, true);
                foreach($data['features'] as $key => $feature) {
                    $data['features'][$key]['properties']['zindex'] = -5;
                }
                $data['properties'] = $properties;
            }
            else {
                $data = [
                    "type"      => "Feature",
                    "geometry"  => [
                        "type"          => "Point",
                        "coordinates"   => [
                            $objElement['geox'], $objElement['geoy']
                        ]
                    ],
                    "properties"    => $properties
                ];
            }
            $content = [
                [
                    "id"                => $objElement['uuid'],
                    "type"              => "GeoJSON",
                    "locationStyle"     => $layerStyle ?  $dataLayer['locstyle'] : $objLocstyle['locstyle'],
                    "data"              => $data,
                    "position"  => [
                        "positionId"    => $objElement['uuid']
                    ],
                    "format"            => "GeoJSON"
                ]
            ];

            $element['content'] = $content;
        }
        $element = array_merge($dataLayer, $element);
        return $element;
    }
}
