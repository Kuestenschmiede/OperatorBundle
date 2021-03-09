<?php


namespace gutesio\OperatorBundle\Classes\Callback;

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
}