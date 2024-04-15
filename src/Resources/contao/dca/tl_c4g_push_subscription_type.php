<?php
use gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback;

use Contao\System;

$str = 'tl_c4g_push_subscription_type';
//Palettes
// only add field if pwa is installed

$packages = System::getContainer()->getParameter('kernel.packages');
if ($packages['con4gis/pwa']) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->addLegend('operator_legend', 'data_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_AFTER, true)
        ->addField('notifyUpcomingEvents', 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
        ->addLegend('operator_legend', 'expert_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE, true)
        ->addField(['notifyUpcomingEvents','gutesioEventTypes'], 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('default', $str);

    $GLOBALS['TL_DCA'][$str]['fields']['notifyUpcomingEvents'] = array
    (
        'exclude' => true,
        'default' => false,
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'clr'],
        'sql' => "char(1) NOT NULL default '0'"
    );

    $GLOBALS['TL_DCA'][$str]['fields']['gutesioEventTypes'] = array
    (
        'label' => &$GLOBALS['TL_LANG'][$str]['gutesioEventTypes'],
        'default' => '-',
        'inputType' => 'select',
        'options_callback' => [GutesioModuleCallback::class, 'getGutesioEventTypes'],
        'eval' => array('mandatory' => false, 'tl_class' => 'clr', 'includeBlankOption' => true, 'multiple' => true, 'chosen' => true),
        'sql' => "blob NULL",
        'exclude' => true
    );
}