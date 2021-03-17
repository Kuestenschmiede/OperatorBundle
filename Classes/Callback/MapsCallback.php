<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes\Callback;

use Contao\DC_Table;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataDirectoryModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataTagModel;
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
            $strSelect = 'SELECT * FROM tl_gutesio_data_tag WHERE published = 1 AND availableInMap = 1';
            $objReturns = $this->Database->prepare($strSelect)->execute()->fetchAllAssoc();
            foreach ($objReturns  as $objReturn) {
                $return[$objReturn['uuid']] = $objReturn['name'];
            }

        }
        else if($dc->filterType == 2) {
            $t = 'tl_gutesio_data_directory';
            $arrOptions = [
                'order' => "$t.name ASC",
            ];

            $objReturns  = GutesioDataDirectoryModel::findAll($arrOptions);
            foreach ($objReturns  as $objReturn) {
                $return[$objReturn->uuid] = $objReturn->name;
            }
        }
        return $return;
    }
}
