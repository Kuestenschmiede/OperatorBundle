<?php
use con4gis\PwaBundle\Classes\Callbacks\PushNotificationCallback;

$str = 'tl_news_archive';
//Palettes
// only add field if operator is installed
$packages = \Contao\System::getContainer()->getParameter('kernel.packages');
if ($packages['gutesio/operator']) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->addLegend('operator_legend', 'expert_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE, true)
        ->addField(['generateGutesBlog'], 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('default', $str);

    // Add the multiple checkbox options
    $GLOBALS['TL_DCA'][$str]['fields']['generateGutesBlog'] = array
    (
        'exclude'                 => true,
        'inputType'               => 'checkbox',
        'options'                 => array(
            'today',
//            'next2days',
//            'next3days',
//            'nextweek',
//            'thismonth',
        ),
        'eval'                    => array('multiple'=>true, 'tl_class'=>'clr'),
        'sql'                     => "blob NULL"
    );

}