<?php
//Palettes
// only add field if operator is installed
use con4gis\CoreBundle\Classes\C4GVersionProvider;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

if (C4GVersionProvider::isInstalled('gutesio/operator')) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->applyToPalette('default', 'tl_c4g_import_data');


    $GLOBALS['TL_DCA']['tl_c4g_import_data']['fields']['type'] = array
    (
        'sql'                     => "varchar(255) NOT NULL",
        'inputType'               => 'select',
        'options'                 => array(
            'demo'                  => &$GLOBALS['TL_LANG']['tl_c4g_import_data']['type_demo'],
            'basedata'              => &$GLOBALS['TL_LANG']['tl_c4g_import_data']['type_basedata'],
            'gutesio'               => &$GLOBALS['TL_LANG']['tl_c4g_import_data']['type_gutesio']
        ),
        'default'                 => '',
        'sorting'                 => true,
        'search'                  => true,
    );

    $GLOBALS['TL_DCA']['tl_c4g_import_data']['fields']['source'] = array
    (
        'sql'                     => "varchar(255) NOT NULL",
        'inputType'               => 'select',
        'options'                 => array(
            'locale'                => &$GLOBALS['TL_LANG']['tl_c4g_import_data']['source_locale'],
            'io'                    => &$GLOBALS['TL_LANG']['tl_c4g_import_data']['source_io'],
            'gutesio'               => &$GLOBALS['TL_LANG']['tl_c4g_import_data']['source_gutesio']
        ),
        'default'                 => '',
        'sorting'                 => true,
        'search'                  => true,
    );
}