<?php

use Contao\System;

$str = 'tl_c4g_push_subscription_type';
//Palettes
// only add field if pwa is installed

$packages = System::getContainer()->getParameter('kernel.packages');
if ($packages['con4gis/pwa']) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->addLegend('operator_legend', 'data_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_AFTER, true)
        ->addField('notifyUpcomingEvents', 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('default', $str);

    $GLOBALS['TL_DCA'][$str]['fields']['notifyUpcomingEvents'] = array
    (
        'exclude' => true,
        'default' => false,
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'clr'],
        'sql' => "char(1) NOT NULL default '0'"
    );

}