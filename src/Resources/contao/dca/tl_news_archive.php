<?php

use con4gis\CoreBundle\Classes\C4GVersionProvider;
use con4gis\PwaBundle\Classes\Callbacks\PushNotificationCallback;
use con4gis\PwaBundle\Classes\Callbacks\PwaConfigurationCallback;


$str = 'tl_news_archive';
//Palettes
// only add field if operator is installed
if (C4GVersionProvider::isInstalled('gutesio/operator')) {
    Contao\CoreBundle\DataContainer\PaletteManipulator::create()
        ->addLegend('operator_legend', 'expert_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE, true)
        ->addField(['generateGutesBlog', 'gutesBlogTitle', 'gutesBlogTeaser'/*, 'gutesBlogImage'*/], 'operator_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
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
        'reference'               => &$GLOBALS['TL_LANG'][$str]['references'],
        'eval'                    => array('multiple'=>true, 'tl_class'=>'clr'),
        'sql'                     => "blob NULL"
    );

    $GLOBALS['TL_DCA'][$str]['fields']['gutesBlogTitle'] = array
    (
        'inputType'               => 'text',
        'eval'                    => ['maxlength'=>42, 'preserve_tags'=>true, 'style'=>'width: calc(100% - 50px); max-height: 480px'],
        'sql'                     => "text NULL",
        'exclude'                 => true
    );

    $GLOBALS['TL_DCA'][$str]['fields']['gutesBlogTeaser'] = array
    (
        'inputType'               => 'textarea',
        'eval'                    => ['maxlength'=>420, 'preserve_tags'=>true, 'style'=>'width: calc(100% - 50px); max-height: 480px'],
        'sql'                     => "text NULL",
        'exclude'                 => true
    );

//    $GLOBALS['TL_DCA'][$str]['fields']['gutesBlogImage'] = array
//    (
//        'label'             => &$GLOBALS['TL_LANG'][$strName]['gutesBlogImage'],
//        'default'           => '',
//        'inputType'         => 'fileTree',
//        'save_callback'     => [[PwaConfigurationCallback::class, 'convertBinToUuid']],
//        'eval'              => ['fieldType'=>'radio', 'files'=>true, 'extensions'=> PwaConfigurationCallback::getAllowedImageExtensions(), 'tl_class'=>'clr', 'mandatory'=>false],
//        'exclude'           => true
//    );

}