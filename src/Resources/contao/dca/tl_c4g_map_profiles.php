<?php
use gutesio\OperatorBundle\Classes\Callback\MapsCallback;

$cbClass = MapsCallback::class;
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['palettes']['default'] = str_replace("{locstyle_legend:hide},label_color,resize_locstyles_zoom;", "{locstyle_legend:hide},label_color,resize_locstyles_zoom;{filter_legend},filterType,filterElements,linkFilterElements;", $GLOBALS['TL_DCA']['tl_c4g_map_profiles']['palettes']['default']);
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['palettes']['default'] = str_replace("{geosearch_legend:hide},geosearch_headline,geosearch_engine,", "{geosearch_legend:hide},geosearch_headline,geosearch_engine,ownGeosearch,showOnlyResults,preventGeosearch,linkGeosearch,", $GLOBALS['TL_DCA']['tl_c4g_map_profiles']['palettes']['default']);
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['fields']['filterType'] = [
    'exclude'                 => true,
    'default'                 => '0',
    'inputType'               => 'radio',
    'options'                 => [0, 1, 2],
    'reference'               => &$GLOBALS['TL_LANG']['tl_c4g_map_profiles']['references']['filterType'],
    'eval'                    => ['mandatory'=>false, 'submitOnChange' => true],
    'sql'                     => "char(1) NOT NULL default '0'"
];
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['fields']['filterElements'] = [
        'exclude'                 => true,
        'inputType'               => 'checkboxWizard',
        'options_callback'        => [$cbClass,'getFilterOptions'],
        'eval'                    => ['mandatory'=>false, 'multiple'=>true, 'helpwizard'=>false],
        'sql'                     => "blob NULL",
];
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['fields']['linkFilterElements'] = [
        'exclude'                 => true,
        'inputType'               => 'multiColumnWizard',
        'eval'                    => ['mandatory'=>false, 'columnsCallback'=>[$cbClass, 'getFilterColumns']],
        'sql'                     => "blob NULL",
];
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['fields']['ownGeosearch'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'sql'                     => "char(1) NOT NULL default ''"
];
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['fields']['showOnlyResults'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'sql'                     => "char(1) NOT NULL default ''"
];
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['fields']['preventGeosearch'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'sql'                     => "char(1) NOT NULL default ''"
];
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['fields']['linkGeosearch'] = [
    'exclude'                 => true,
    'inputType'               => 'multiColumnWizard',
    'eval'                    => ['mandatory'=>false, 'columnFields'=> [
        'linkText' => [
            'label'         => &$GLOBALS['TL_LANG']['tl_c4g_map_profiles']['linkText'],
            'exclude'       => true,
            'inputType'     => 'text',
            'eval' 			=> ['tl_class'=>'w50']
        ],
        'link' => [
            'label'         => &$GLOBALS['TL_LANG']['tl_c4g_map_profiles']['link'],
            'exclude'       => true,
            'inputType'     => 'pageTree',
            'eval' 			=> ['tl_class'=>'w50']
        ],
    ]],
    'sql'                     => "blob NULL",
];