<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Callback;

use con4gis\MapsBundle\Resources\contao\models\C4gMapsModel;
use Contao\Controller;
use Contao\DataContainer;
use Contao\Message;
use Contao\ModuleModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildTypeModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataDirectoryModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataTagModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataTypeModel;

class GutesioModuleCallback
{
    public function getMapContentElements()
    {
        $maps = \ContentModel::findBy('type', 'c4g_maps');
        $resultList = [];
        foreach ($maps as $map) {
            $map->name = C4gMapsModel::findByPk($map->c4g_map_id)->name;
            if ($map->name) {
                $resultList[$map->id] = $map->name;
            }
        }

        return $resultList;
    }

    public function getDirectoryOptions()
    {
        $arrDirectories = GutesioDataDirectoryModel::findAll();
        $arrDirectories = $arrDirectories ? $arrDirectories->fetchAll() : [];
        $options = [];
        foreach ($arrDirectories as $directory) {
            $options[$directory['uuid']] = $directory['name'];
        }

        return $options;
    }

    public function getTypeOptions()
    {
        $arrTypes = GutesioDataTypeModel::findAll();
        $arrTypes = $arrTypes ? $arrTypes->fetchAll() : [];
        $options = [];
        foreach ($arrTypes as $type) {
            $options[$type['uuid']] = $type['name'];
        }

        return $options;
    }

    public function getTagOptions()
    {
        $options = [];

        $arrTags = GutesioDataTagModel::findBy('published', 1);
        if ($arrTags) {
            $arrTags = $arrTags->fetchAll();
            foreach ($arrTags as $tag) {
                if ($tag['technicalKey'] !== 'tag_opening_hours') {
                    $options[$tag['uuid']] = $tag['name'];
                }
            }
        }

        return $options;
    }

    public function getLayoutOptions(DataContainer $dc)
    {
        if ($dc->id) {
            $objData = ModuleModel::findByPk($dc->id);
            if ($objData === null) {
                return [];
            }
        }
        if ($objData->type === 'showcase_list_module') {
            return [
                'plain',
                'list',
                'grid',
            ];
        }

        return [
                'plain',
                'list',
                'grid',
            ];
    }

    public function getCategoryOptions()
    {
        $arrTypes = GutesioDataChildTypeModel::findAll();
        $arrTypes = $arrTypes ? $arrTypes->fetchAll() : [];
        $options = [];
        foreach ($arrTypes as $type) {
            $options[$type['uuid']] = $type['name'];
        }

        return $options;
    }

    public function setHeadlineHint(DataContainer $dc)
    {
        $listModuleTypes = [
            'showcase_list_module',
            'showcase_child_list_module',
            'showcase_carousel_module',
        ];

        if ($dc->id) {
            $objDc = ModuleModel::findByPk($dc->id);
            $type = $objDc->type;
            if (in_array($type, $listModuleTypes)) {
                $message = '';
                if ($type !== 'showcase_carousel_module') {
                    $message .= $GLOBALS['TL_LANG']['tl_module']['optional_heading_hint'];
                }
                $message .= $GLOBALS['TL_LANG']['tl_module']['gutes_heading_hint'];
                Message::addInfo($message);
            }
        }
    }

    public function getCarouselTemplateOptions()
    {
        return Controller::getTemplateGroup('mod_gutesio_showcase_carousel_module_');
    }
}
