<?php

$str = 'tl_c4g_push_subscription_type';
//Palettes
// only add field if operator is installed
$packages = \Contao\System::getContainer()->getParameter('kernel.packages');
if ($packages['gutesio/operator']) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->addLegend('operator_legend', 'expert_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE, true)
        ->addField(['notifyUpcomingEvents'], 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
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