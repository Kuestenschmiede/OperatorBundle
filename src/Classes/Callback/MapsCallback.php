<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Callback;

use Contao\DC_Table;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataDirectoryModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataTypeModel;
use Contao\Backend;

class MapsCallback extends Backend
{
    public function getConfiguredTypes()
    {
        $arrTypes = [];
        $t = 'tl_gutesio_data_type';
        $arrOptions = [
            'order' => "$t.name ASC",
        ];
        $types = GutesioDataTypeModel::findAll($arrOptions);
        foreach ($types as $type) {
            $arrTypes[$type->uuid] = $type->name;
        }

        return $arrTypes;
    }

    public function getConfiguredDirectories()
    {
        $arrTypes = [];
        $t = 'tl_gutesio_data_directory';
        $arrOptions = [
            'order' => "$t.name ASC",
        ];
        $types = GutesioDataDirectoryModel::findAll($arrOptions);
        foreach ($types as $type) {
            $arrTypes[$type->uuid] = $type->name;
        }

        return $arrTypes;
    }
    public function getFilterOptions($dc)
    {
        if ($dc instanceof DC_Table) {
            $dc = $dc->activeRecord;
        }
        if ($dc->filterType == 1) {
            $strSelect = 'SELECT * FROM tl_gutesio_data_tag WHERE published = 1';
            $objReturns = $this->Database->prepare($strSelect)->execute()->fetchAllAssoc();
            foreach ($objReturns  as $objReturn) {
                $return[$objReturn['uuid']] = $objReturn['name'];
            }
        } elseif ($dc->filterType == 2) {
            $t = 'tl_gutesio_data_directory';
            $arrOptions = [
                'order' => "$t.name ASC",
            ];

            $objReturns = GutesioDataDirectoryModel::findAll($arrOptions);
            foreach ($objReturns  as $objReturn) {
                $return[$objReturn->uuid] = $objReturn->name;
            }
        }

        return $return;
    }
    public function getFilterColumns()
    {
        $strSelect = 'SELECT * FROM tl_gutesio_data_tag WHERE published = 1';
        $options = [];
        $objOptions = $this->Database->prepare($strSelect)->execute()->fetchAllAssoc();
        foreach ($objOptions  as $objOption) {
            $options[$objOption['uuid']] = $objOption['name'];
        }
        $return = [
            'filterOption' => [
                'label'                 => &$GLOBALS['TL_LANG']['tl_c4g_map_profiles']['filterOption'],
                'exclude'               => true,
                'inputType'             => 'select',
                'options'            	=> $options,
                'eval' 			        => ['style' => 'width:250px', 'includeBlankOption'=>false, 'chosen'=>true]
            ],
            'filterLink' => [
                'label'                 => &$GLOBALS['TL_LANG']['tl_c4g_map_profiles']['filterLink'],
                'exclude'               => true,
                'inputType'             => 'text',
                'eval'                  => ['style'=>'width:200px']
            ]
        ];
        return $return;
    }
    public function getLocStyles()
    {
        $locStyles = $this->Database->prepare("SELECT id,name FROM tl_c4g_map_locstyles ORDER BY name")->execute();
        while ($locStyles->next()) {
            $return[$locStyles->id] = $locStyles->name;
        }
        return $return;
    }
}
