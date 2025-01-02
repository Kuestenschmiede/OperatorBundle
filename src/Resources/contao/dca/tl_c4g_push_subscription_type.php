<?php

use con4gis\CoreBundle\Classes\C4GVersionProvider;
use gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback;

use Contao\System;

$str = 'tl_c4g_push_subscription_type';
//Palettes
// only add field if pwa is installed

if (C4GVersionProvider::isInstalled('con4gis/pwa')) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->addLegend('operator_legend', 'data_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_AFTER, true)
        ->addLegend('operator_legend', 'expert_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE, true)
        ->addField([/*'notifyUpcomingEvents', */'gutesioEventTypes'], 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('default', $str);

//    $GLOBALS['TL_DCA'][$str]['fields']['notifyUpcomingEvents'] = array
//    (
//        'default' => false,
//        'inputType' => 'checkbox',
////        'eval' => ['tl_class' => 'clr'],
//        'sql' => "char(1) NOT NULL",
//        'exclude' => true
//    );

    $GLOBALS['TL_DCA'][$str]['fields']['gutesioEventTypes'] = array
    (

        'label'                   => &$GLOBALS['TL_LANG'][$str]['gutesioEventTypes'],
        'inputType'               => 'select',
        'exclude'                 => true,
        'default'                 => '-',
        'options_callback'        => [GutesioModuleCallback::class, 'getGutesioEventTypes'],
        'eval'                    => array('chosen'=>true,'mandatory'=>false,'multiple'=>true, 'tl_class'=>'long clr','alwaysSave'=> true),
        'sql'                     => "blob not NULL"
    );
}