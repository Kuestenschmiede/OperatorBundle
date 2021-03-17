<?php
use gutesio\OperatorBundle\Classes\Callback\MapsCallback;

$cbClass = MapsCallback::class;
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['palettes']['default'] = str_replace("{locstyle_legend:hide},label_color,resize_locstyles_zoom;", "{locstyle_legend:hide},label_color,resize_locstyles_zoom;{filter_legend},filterType,filterElements;", $GLOBALS['TL_DCA']['tl_c4g_map_profiles']['palettes']['default']);
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['fields']['filterType'] = [
    'label'                   => &$GLOBALS['TL_LANG']['tl_c4g_map_profiles']['filterType'],
    'exclude'                 => true,
    'default'                 => 'CLICK',
    'inputType'               => 'radio',
    'options'                 => [0, 1, 2],
    'reference'               => &$GLOBALS['TL_LANG']['tl_c4g_map_profiles']['references']['filterType'],
    'eval'                    => ['mandatory'=>false, 'submitOnChange' => true],
    'sql'                     => "char(1) NOT NULL default '0'"
];
$GLOBALS['TL_DCA']['tl_c4g_map_profiles']['fields']['filterElements'] = [
        'label'                   => &$GLOBALS['TL_LANG']['tl_c4g_map_profiles']['filterElements'],
        'exclude'                 => true,
        'inputType'               => 'checkbox',
        'options_callback'        => [$cbClass,'getFilterOptions'],
        'eval'                    => ['mandatory'=>false, 'multiple'=>true],
        'sql'                     => "blob NULL",
];