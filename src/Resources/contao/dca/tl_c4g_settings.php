<?php

//Palettes
// only add field if operator is installed
use con4gis\CoreBundle\Classes\C4GVersionProvider;

if (C4GVersionProvider::isInstalled('gutesio/operator')) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->addLegend('operator_legend', 'expert_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE, true)
        ->addField(['syncDataAutomaticly', 'updateSearchIndex', 'deleteSearchIndex'], 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('default', 'tl_c4g_settings');


    $GLOBALS['TL_DCA']['tl_c4g_settings']['fields']['syncDataAutomaticly'] = array
    (
        'exclude'                 => true,
        'default'                 => true,
        'inputType'               => 'checkbox',
        'eval'                    => ['tl_class'=>'clr'],
        'sql'                     => "char(1) NOT NULL default '1'"
    );

    $GLOBALS['TL_DCA']['tl_c4g_settings']['fields']['updateSearchIndex'] = array
    (
        'exclude'                 => true,
        'default'                 => true,
        'inputType'               => 'checkbox',
        'eval'                    => ['tl_class'=>'clr'],
        'sql'                     => "char(1) NOT NULL default '1'"
    );

    $GLOBALS['TL_DCA']['tl_c4g_settings']['fields']['deleteSearchIndex'] = array
    (
        'exclude'                 => true,
        'default'                 => true,
        'inputType'               => 'checkbox',
        'eval'                    => ['tl_class'=>'clr'],
        'sql'                     => "char(1) NOT NULL default '1'"
    );
}