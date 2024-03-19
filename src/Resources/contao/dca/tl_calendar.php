<?php

$str = 'tl_calendar';
//Palettes
// only add field if operator is installed
$packages = \Contao\System::getContainer()->getParameter('kernel.packages');
if ($packages['gutesio/operator']) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->addLegend('operator_legend', 'expert_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE, true)
        ->addField(['pushUpcomingEvents'], 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('default', $str);


    $GLOBALS['TL_DCA'][$str]['fields']['pushUpcomingEvents'] = array
    (
        'exclude'                 => true,
        'default'                 => true,
        'inputType'               => 'checkbox',
        'eval'                    => ['tl_class'=>'clr'],
        'sql'                     => "char(1) NOT NULL default '0'"
    );

}