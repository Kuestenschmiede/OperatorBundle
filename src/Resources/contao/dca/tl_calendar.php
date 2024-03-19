<?php
use con4gis\PwaBundle\Classes\Callbacks\PushNotificationCallback;

$str = 'tl_calendar';
//Palettes
// only add field if operator is installed
$packages = \Contao\System::getContainer()->getParameter('kernel.packages');
if ($packages['gutesio/operator']) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->addLegend('operator_legend', 'expert_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE, true)
        ->addField(['pushUpcomingEvents','subscriptionTypes'], 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('default', $str);


    $GLOBALS['TL_DCA'][$str]['fields']['pushUpcomingEvents'] = array
    (
        'exclude'                 => true,
        'default'                 => false,
        'inputType'               => 'checkbox',
        'eval'                    => ['tl_class'=>'clr'],
        'sql'                     => "char(1) NOT NULL default '0'"
    );

    $GLOBALS['TL_DCA'][$str]['fields']['subscriptionTypes'] = [
        'label' => &$GLOBALS['TL_LANG'][$str]['subscriptionTypes'],
        'default' => [],
        'inputType' => 'select',
        'options_callback' => [PushNotificationCallback::class, 'getSubscriptionTypes'],
        'eval' => array('mandatory' => false, 'tl_class' => 'clr', 'includeBlankOption' => true, 'multiple' => true, 'chosen' => true),
        'sql' => "blob NULL",
        'exclude' => true
    ];

}