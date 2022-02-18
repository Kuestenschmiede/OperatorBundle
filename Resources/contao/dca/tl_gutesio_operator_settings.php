<?php

/**
 * This file is part of con4gis,
 * the gis-kit for Contao CMS.
 *
 * @package    con4gis
 * @version        7
 * @author        con4gis contributors (see "authors.txt")
 * @license        LGPL-3.0-or-later
 * @copyright    Küstenschmiede GmbH Software & Design
 * @link              https://www.con4gis.org
 *
 */

$strName = "tl_gutesio_operator_settings";
$cbClass = \gutesio\OperatorBundle\Classes\Callback\GutesioOperatorSettingCallback::class;

$GLOBALS['TL_DCA'][$strName] = [
    'config' => [
        'label' => &$GLOBALS['TL_LANG']['MOD'][$strName][0],
        'dataContainer' => 'Table',
        'enableVersioning' => false,
        'notDeletable' => true,
        'notCopyable' => true,
        'onload_callback' => [[$cbClass, 'redirectToDetails']],
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ],
        ],
    ],
    
    'palettes' => [
        'default' => '{key_legend},gutesIoUrl,gutesIoKey;'.
            '{map_legend},detail_profile,detail_map;'.
            '{page_legend},showcaseDetailPage,productDetailPage,'.
            'jobDetailPage,eventDetailPage,arrangementDetailPage,serviceDetailPage,voucherDetailPage,cartPage;'
    ],
    
    'fields' => [
        'id' => [
            'sql' => 'int unsigned NOT NULL auto_increment'
        ],
        'tstamp' => [
            'sql' => 'int unsigned NOT NULL default 0'
        ],
        'gutesIoUrl' =>[
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => ['mandatory' => true, 'maxlength' => 100, 'doNotSaveEmpty' => true],
            'load_callback'           => [[$cbClass, 'loadIoUrl']],
            'save_callback'           => [[$cbClass, 'saveIoUrl']]
        ],
        'gutesIoKey' => [
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => ['mandatory' => true, 'maxlength' => 32, 'doNotSaveEmpty' => true],
            'load_callback'           => [[$cbClass, 'loadIoKey']],
            'save_callback'           => [[$cbClass, 'saveIoKey']]
        ],
        'detail_profile' => [
            'exclude'                 => true,
            'inputType'               => 'select',
            'foreignKey'              => 'tl_c4g_map_profiles.name',
            'eval'                    => ['tl_class'=>'clr', 'includeBlankOption'=>true,  'chosen'=>true, 'blankOptionLabel'=>"-",
                                            'submitOnChange' => true, 'alwaysSave' => true],
            'relation'                => ['type'=>'belongsTo', 'load'=>'eager'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
        ],
        'detail_map' => [
            'exclude'                 => true,
            'inputType'               => 'select',
            'eval'                    => ['tl_class'=>'clr', 'includeBlankOption'=>true,  'chosen'=>true, 'blankOptionLabel'=>"-",
                                            'submitOnChange' => true, 'alwaysSave' => true],
            'options_callback' => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getMapContentElements"],
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
        ],
        'showcaseDetailPage' => [
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'productDetailPage' => [
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'jobDetailPage' => [
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'eventDetailPage' => [
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'arrangementDetailPage' => [
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'serviceDetailPage' => [
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'voucherDetailPage' => [
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'cartPage' => [
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'taxRegular' => [
            'exclude'                 => true,
            'default'                 => '19',
            'inputType'               => 'text',
            'eval'                    => ['fieldType' => 'digit', 'mandatory' => true, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '19'"
        ],
        'taxReduced' => [
            'exclude'                 => true,
            'default'                 => '7',
            'inputType'               => 'text',
            'eval'                    => ['fieldType' => 'digit', 'mandatory' => true, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '7'"
        ]
    ],
];