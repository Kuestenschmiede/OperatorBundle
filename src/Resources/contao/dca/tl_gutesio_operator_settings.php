<?php

/**
 * This file is part of con4gis,
 * the gis-kit for Contao CMS.
 *
 * @package    con4gis
 * @author        con4gis contributors (see "authors.md")
 * @license        LGPL-3.0-or-later
 * @copyright    Küstenschmiede GmbH Software & Design
 * @link              https://www.con4gis.org
 *
 */

use Contao\DC_Table;
use gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback;

$strName = "tl_gutesio_operator_settings";
$cbClass = \gutesio\OperatorBundle\Classes\Callback\GutesioOperatorSettingCallback::class;

$GLOBALS['TL_DCA'][$strName] = [
    'config' => [
        'label' => &$GLOBALS['TL_LANG']['MOD'][$strName][0],
        'dataContainer' => DC_Table::class,
        'enableVersioning' => false,
        'notDeletable' => true,
        'notCopyable' => true,
        'onload_callback' => [
            [$cbClass, 'redirectToDetails'],
            [$cbClass, 'deleteMainServerUrl']
        ],
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ],
        ],
    ],

    'list' => [
        'label' => [
            'fields' => ['']
        ]
    ], //prevent cto5 error

    'palettes' => [
        'default' => '{key_legend},cdnUrl,gutesIoUrl,gutesIoKey;'.
            '{map_legend},detail_profile,detail_map,popupFields,popupFieldsReduced;'.
            '{page_legend},showcaseDetailPage,productDetailPage,'.
            'jobDetailPage,eventDetailPage,arrangementDetailPage,serviceDetailPage,personDetailPage,voucherDetailPage,realestateDetailPage,exhibitionDetailPage,cartPage,ratingPage;'.
            '{pwa_legend},dailyEventPushConfig;'.
            '{ai_legend},aiEnabled,aiAssistantName,aiApiEndpoint,aiApiKey,aiModel,aiMaxContextRecords,aiAdditionalKnowledge;'
    ],

    'fields' => [
        'id' => [
            'sql' => 'int unsigned NOT NULL auto_increment'
        ],
        'tstamp' => [
            'sql' => 'int unsigned NOT NULL default 0'
        ],
        'aiAssistantName' => [
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => ['tl_class' => 'w50', 'maxlength' => 50],
            'sql'                     => "varchar(50) NOT NULL default 'KI'"
        ],
        'cdnUrl' =>[
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 100, 'doNotSaveEmpty' => true],
            'sql' => "varchar(255) NOT NULL default ''"
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
            'eval'                    => ['mandatory' => true, 'maxlength' => 34, 'doNotSaveEmpty' => true],
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
        'popupFields' => [
            'exclude'                 => true,
            'inputType'               => 'select',
            'eval'                    => [
                'mandatory' => false,
                'chosen'    => true,
                'multiple'  => true
            ],
            'options'                 => &$GLOBALS['TL_LANG']['tl_gutesio_operator_settings']['popupFieldsRefs'],
            'sql'                     => "blob NULL",
        ],
        'popupFieldsReduced' => [
            'exclude'                 => true,
            'inputType'               => 'select',
            'eval'                    => [
                'mandatory' => false,
                'chosen'    => true,
                'multiple'  => true
            ],
            'options'                 => &$GLOBALS['TL_LANG']['tl_gutesio_operator_settings']['popupFieldsRefs'],
            'sql'                     => "blob NULL",
        ],
        'detail_map' => [
            'exclude'                 => true,
            'inputType'               => 'select',
            'eval'                    => ['tl_class'=>'clr', 'includeBlankOption'=>true,  'chosen'=>true, 'blankOptionLabel'=>"-",
                'submitOnChange' => true, 'alwaysSave' => true],
            'options_callback' => [GutesioModuleCallback::class, "getMapContentElements"],
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
        'personDetailPage' => [
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
        'realestateDetailPage' => [
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'exhibitionDetailPage' => [
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
        'ratingPage' => [
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => ['mandatory' => false, 'tl_class' => 'clr'],
            'sql'                     => "varchar(255) NOT NULL default ''"
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
        ],
        'mainServerUrl' => [
            'sql'                     => "varchar(50) NOT NULL default ''"
        ],

        'dailyEventPushConfig' => [
            'label'     => &$GLOBALS['TL_LANG'][$strName]['dailyEventPushConfig'],
            'exclude'   => true,
            'inputType' => 'multiColumnWizard',
            'eval'      => [
                'columnFields' => [
                    'pushTime'      => [
                        'label'     => &$GLOBALS['TL_LANG'][$strName]['pushTime'],
                        'exclude'   => true,
                        'inputType' => 'text',
                    ],
                    'pushMessage' => [
                        'label'     => &$GLOBALS['TL_LANG'][$strName]['pushMessage'],
                        'exclude'   => true,
                        'inputType' => 'text',
                        'eval'      => [ 'style' => 'width: 150px;' ],
                    ],
                    'subscriptionTypes' => [
                        'label'     => &$GLOBALS['TL_LANG'][$strName]['subscriptionTypes'],
                        'exclude'   => true,
                        'inputType' => 'select',
                        'eval'      => [ 'multiple' => true, 'chosen' => true, 'style'=> 'width: 250px;' ],
                        'foreignKey'              => 'tl_c4g_push_subscription_type.name',
                        'relation'                => ['type'=>'belongsTo', 'load'=>'eager'],
//                        'options_callback' => [GutesioModuleCallback::class, "getSubscriptionTypes"],
                    ],
                    'pushRedirectPage' => [
                        'label'     => &$GLOBALS['TL_LANG'][$strName]['pushRedirectPage'],
                        'exclude'                 => true,
                        'inputType'               => 'pageTree',
                        'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
                        'sql'                     => "int(10) unsigned NOT NULL default '0'"
                    ],
                    'sendForAllEventTypes' => [
                        'label' => &$GLOBALS['TL_LANG'][$strName]['sendForAllEventTypes'],
                        'exclude'                 => true,
                        'inputType'               => 'checkbox',
                        'eval'                    => ['mandatory' => false],
                        'sql'                     => "int(10) unsigned NOT NULL default 0"
                    ]
                ],
            ],
            'sql'       => 'blob NULL',
        ],
        'aiEnabled' => [
            'exclude'                 => true,
            'inputType'               => 'checkbox',
            'eval'                    => ['tl_class' => 'w50', 'submitOnChange' => true],
            'sql'                     => "char(1) NOT NULL default ''"
        ],
        'aiApiEndpoint' => [
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => ['tl_class' => 'w50', 'maxlength' => 255],
            'sql'                     => "varchar(255) NOT NULL default ''"
        ],
        'aiApiKey' => [
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => ['tl_class' => 'w50', 'maxlength' => 255, 'hideInput' => true],
            'sql'                     => "varchar(255) NOT NULL default ''"
        ],
        'aiModel' => [
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => ['tl_class' => 'w50', 'maxlength' => 50],
            'sql'                     => "varchar(50) NOT NULL default ''"
        ],
        'aiMaxContextRecords' => [
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => ['rgxp' => 'digit', 'mandatory' => false, 'tl_class' => 'w50'],
            'sql'                     => "int(10) unsigned NOT NULL default 0"
        ],
        'aiAdditionalKnowledge' => [
            'exclude'                 => true,
            'inputType'               => 'textarea',
            'eval'                    => ['tl_class' => 'clr', 'rte' => 'ace|markdown'],
            'sql'                     => "text NULL"
        ],
        'aiSystemPrompt' => [
            'exclude'                 => true,
            'inputType'               => 'textarea',
            'eval'                    => ['tl_class' => 'clr', 'rte' => 'ace|markdown'],
            'sql'                     => "text NULL"
        ],
        'aiSyncConfig' => [
            'exclude'                 => true,
            'inputType'               => 'multiColumnWizard',
            'eval'                    => [
                'columnFields' => [
                    'table' => [
                        'label'     => &$GLOBALS['TL_LANG'][$strName]['aiSyncTable'],
                        'exclude'   => true,
                        'inputType' => 'select',
                        'options'   => [
                            'tl_gutesio_data_element',
                            'tl_gutesio_data_child',
                            'tl_gutesio_data_child_product',
                            'tl_gutesio_data_child_event',
                            'tl_gutesio_data_child_job',
                            'tl_gutesio_data_child_voucher',
                            'tl_gutesio_data_child_person',
                            'tl_gutesio_data_child_arrangement',
                            'tl_gutesio_data_child_service'
                        ],
                        'eval'      => ['style' => 'width: 250px;', 'includeBlankOption' => true, 'chosen' => true]
                    ],
                    'fields' => [
                        'label'     => &$GLOBALS['TL_LANG'][$strName]['aiSyncFields'],
                        'exclude'   => true,
                        'inputType' => 'text',
                        'eval'      => ['style' => 'width: 400px;']
                    ]
                ]
            ],
            'sql'                     => "blob NULL"
        ]

    ],
];