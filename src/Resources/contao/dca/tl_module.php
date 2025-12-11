<?php
/**
 * This file is part of con4gis,
 * the gis-kit for Contao CMS.
 *
 * @package   	con4gis
 * @version        6
 * @author  	    con4gis contributors (see "authors.txt")
 * @license 	    LGPL-3.0-or-later
 * @copyright 	Küstenschmiede GmbH Software & Design
 * @link              https://www.con4gis.org
 *
 */


$GLOBALS['TL_DCA']['tl_module']['config']['onload_callback'][] = [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, 'setHeadlineHint'];

$GLOBALS['TL_DCA']['tl_module']['palettes']['showcase_list_module'] =
    '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_render_searchHtml,gutesio_data_layoutType,'.
    'gutesio_data_redirect_page,gutesio_data_show_details,gutesio_data_limit,'.
    'gutesio_data_max_data,gutesio_data_mode,gutesio_data_restrict_postals,gutesio_data_show_image,gutesio_data_show_category,gutesio_data_show_city,gutesio_data_show_selfHelpFocus;' .
    '{showcase_filter_legend},gutesio_initial_sorting,gutesio_enable_filter,gutesio_enable_ext_filter,gutesio_data_change_layout_filter,gutesio_enable_tag_filter,gutesio_enable_type_filter,gutesio_enable_location_filter,gutesio_disable_sorting_filter;'.
    '{performance_legend},gutesio_strict_images;';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_data_mode';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_0'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_1'] = 'gutesio_data_type';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_2'] = 'gutesio_data_directory';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_3'] = 'gutesio_data_tags';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_4'] = 'gutesio_data_blocked_types';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_5'] = 'gutesio_data_elements';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_enable_tag_filter';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_enable_tag_filter'] = 'gutesio_tag_filter_selection';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_enable_type_filter';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_enable_type_filter'] = 'gutesio_type_filter_selection';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_enable_category_filter';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_enable_category_filter'] = 'gutesio_category_filter_selection';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_data_change_layout_filter';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_change_layout_filter'] = 'gutesio_data_layout_filter';

$GLOBALS['TL_DCA']['tl_module']['palettes']['showcase_detail_module'] =
    '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_render_searchHtml,gutesio_showcase_list_page,gutesio_load_klaro_consent,gutesio_data_mode,customTpl;'.
    '{content_legend},gutesio_without_tiles,gutesio_data_max_data;'.
    '{cart_legend},cart_page'
;

$GLOBALS['TL_DCA']['tl_module']['palettes']['offer_list_module'] =
    '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_layoutType,gutesio_child_showcase_link,gutesio_data_render_searchHtml;'.
    '{load_legend},gutesio_child_data_mode,gutesio_data_elements,gutesio_data_limit,gutesio_data_max_data,gutesio_child_sort_by_date,gutesio_hide_events_without_date, gutesio_data_show_city;'.
    '{cart_legend},cart_page;'.
    '{showcase_filter_legend},gutesio_enable_filter,gutesio_enable_location_filter,gutesio_enable_ext_filter,gutesio_child_search_label,gutesio_child_search_placeholder,'.
    'gutesio_child_search_description,gutesio_child_text_search,gutesio_child_text_no_results,'.
    'gutesio_child_filter,gutesio_data_change_layout_filter,gutesio_enable_tag_filter, gutesio_enable_category_filter,gutesio_disable_sorting_filter;'.
    '{performance_legend},gutesio_strict_images;';

$GLOBALS['TL_DCA']['tl_module']['palettes']['offer_detail_module'] =
    '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_render_searchHtml,gutesio_offer_list_page,gutesio_without_tiles,limit_detail_offers,gutesio_without_contact,gutesio_child_data_mode;{cart_legend},cart_page,customTpl;'
;

$GLOBALS['TL_DCA']['tl_module']['palettes']['cart_module'] =
    '{title_legend},name,headline,type,cart_no_items_text;'
;

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_child_data_mode';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_child_data_mode_0'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_child_data_mode_1'] = 'gutesio_child_type';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_child_data_mode_2'] = 'gutesio_child_category';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_child_data_mode_3'] = 'gutesio_child_tag';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_child_data_mode_4'] = 'gutesio_child_selection';;

$GLOBALS['TL_DCA']['tl_module']['palettes']['showcase_carousel_module'] = '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_redirect_page,gutesio_data_max_data,gutesio_data_mode,gutesio_data_restrict_postals,gutesio_carousel_template;';

$GLOBALS['TL_DCA']['tl_module']['palettes']['wishlist_module'] = '{title_legend},name,headline,type,gutesio_show_contact_data,gutesio_data_show_image,gutesio_data_show_category,gutesio_data_show_selfHelpFocus;{cart_legend},cart_page;';

$GLOBALS['TL_DCA']['tl_module']['palettes']['banner_module'] = '{title_legend},name,headline,type;'.
        '{load_legend},gutesio_data_mode,gutesio_child_data_mode,gutesio_max_childs,lazyBanner,reloadBanner,loadMonth,gutesio_banner_folder,gutesio_banner_skip_unlinked;'.
        '{appearance_legend},gutesio_banner_fullscreen,gutesio_banner_height_value,gutesio_banner_height_unit,gutesio_banner_width_value,gutesio_banner_width_unit,gutesio_banner_theme_color,gutesio_banner_hide_poweredby,gutesio_banner_media_bg_portrait,gutesio_banner_hide_event_endtime,gutesio_banner_footer_align_left,gutesio_banner_show_ad_label,gutesio_banner_links_new_tab;'.
        '{performance_legend},gutesio_banner_lazy_mode,gutesio_banner_limit_initial,gutesio_banner_defer_assets,gutesio_banner_defer_qr,gutesio_banner_qr_for_images,gutesio_banner_interval,gutesio_banner_strict_images;';
$GLOBALS['TL_DCA']['tl_module']['palettes']['banner_module'] .= '{security_legend},gutesio_banner_guard_param,gutesio_banner_guard_value;';
$GLOBALS['TL_DCA']['tl_module']['palettes']['nearby_showcase_list_module'] = '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_mode,gutesio_data_redirect_page,gutesio_data_max_data,gutesio_check_position,gutesio_show_detail_link;';

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_mode'] = [
    'exclude'                 => true,
    'default'                 => "0",
    'inputType'               => 'radio',
    'options'                 => ['0', '1', '2', '3', '4', '5', '6'],
    'reference'               => &$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option'],
    'sql'                     => "char(1) NOT NULL default '0'",
    'eval'                    => ['submitOnChange' => true, 'tl_class' => "clr"]
];

// Viewport size configuration for banner
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_height_value'] = [
    'exclude' => true,
    'label'   => ['Viewport-Höhe', 'Numerischer Wert für die Höhe des Banners (z. B. 60). In Kombination mit der Einheit. Bei Vollbild wird ignoriert.'],
    'inputType' => 'text',
    'eval' => ['rgxp' => 'digit', 'maxlength' => 5, 'tl_class' => 'clr w50'],
    'sql'  => "varchar(5) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_height_unit'] = [
    'exclude' => true,
    'label'   => ['Höheneinheit', 'Einheit für die Bannerhöhe.'],
    'inputType' => 'select',
    'options' => ['vh', 'px', 'rem', '%'],
    'reference' => [
        'vh' => 'vh',
        'px' => 'px',
        'rem' => 'rem',
        '%' => '%',
    ],
    'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql'  => "varchar(5) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_width_value'] = [
    'exclude' => true,
    'label'   => ['Viewport-Breite', 'Numerischer Wert für die Breite des Banners (optional). In Kombination mit der Einheit. Standard ist 100%.'],
    'inputType' => 'text',
    'eval' => ['rgxp' => 'digit', 'maxlength' => 5, 'tl_class' => 'clr w50'],
    'sql'  => "varchar(5) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_width_unit'] = [
    'exclude' => true,
    'label'   => ['Breiteneinheit', 'Einheit für die Bannerbreite.'],
    'inputType' => 'select',
    'options' => ['%', 'px', 'vw', 'rem'],
    'reference' => [
        '%' => '%',
        'px' => 'px',
        'vw' => 'vw',
        'rem' => 'rem',
    ],
    'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql'  => "varchar(5) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_type'] = [
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getTypeOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'                     => "blob NULL"
];

// Banner: Hide end time for events (show only start time)
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_hide_event_endtime'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_hide_event_endtime'],
    'default'   => '1',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '1'",
];

// Banner: Option to align footer contact+logo to the left
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_footer_align_left'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_footer_align_left'],
    'default'   => '1',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '1'",
];

// Banner: Optionales Werbelabel „Anzeige“ ein-/ausblenden
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_show_ad_label'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_show_ad_label'],
    'default'   => '0',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '0'",
];

// Banner: Wechselintervall der Slides (in Millisekunden)
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_interval'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_interval'],
    // Standard entspricht der bisherigen Hardcodierung im Template (15000 ms)
    'default'   => '15000',
    'eval'      => ['rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'w50', 'helpwizard' => false],
    'sql'       => "varchar(6) NOT NULL default '15000'",
];

// Banner: Theme-Farbe für Overlays/Badge/Footer (Hex)
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_theme_color'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_theme_color'],
    'default'   => '#2ea1db',
    'eval'      => [
        'maxlength' => 7,
        'tl_class'  => 'clr w50',
        'colorpicker' => true,
        'isHexColor'  => true,
        'decodeEntities' => true,
        'rgxp' => 'custom'
    ],
    'sql'       => "varchar(7) NOT NULL default '#2ea1db'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_type_filter_selection'] = [
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getTypeOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'                     => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_directory'] = [
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getDirectoryOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'                     => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_tags'] = [
    'default'                 => null,
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getTagOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'                     => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_elements'] = [
    'default' => '',
    'exclude' => true,
    'inputType' => 'select',
    'filter' => true,
    'sorting' => true,
    'search' => true,
    'options_callback' => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, 'loadShowcaseOptions'],
    'eval' => [
        'mandatory' => false,
        'multiple' => true,
        'tl_class' => 'clr',
        'chosen' => true,
        'includeBlankOption' => true
    ],
    'sql' => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_restrict_postals'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => ['tl_class' => 'clr'],
    'sql'                     => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_blocked_types'] = [
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getTypeOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'                     => "text NULL"
];


$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_layoutType'] = [
    'exclude'                 => true,
    'inputType'               => 'select',
    'default'                 => 'grid',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getLayoutOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => false, 'tl_class' => 'clr'],
    'reference'               => &$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType']['options'],
    'sql'                     => "varchar(25) NOT NULL default 'grid'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_redirect_page'] = [
    'exclude'                 => true,
    'inputType'               => 'pageTree',
    'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
    'sql'                     => "int(10) unsigned NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_showcase_list_page'] = [
    'exclude'                 => true,
    'inputType'               => 'pageTree',
    'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
    'sql'                     => "int(10) unsigned NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_offer_list_page'] = [
    'exclude'                 => true,
    'inputType'               => 'pageTree',
    'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
    'sql'                     => "int(10) unsigned NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_show_details'] = [
    'exclude'                 => true,
    'default'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_initial_sorting'] = [
    'label'                   => &$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting'],
    'exclude'                 => true,
    'default'                 => "random",
    'inputType'               => 'radio',
    'options'                 => ['random', 'name_asc', 'name_desc', 'tstamp_desc', 'distance'],
    'reference'               => &$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option'],
    'sql'                     => "char(15) NOT NULL default 'random'",
    'eval'                    => ['submitOnChange' => true, 'tl_class' => "clr"]
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_enable_filter'] = [
    'exclude'                 => true,
    'default'                 => '1',
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_enable_ext_filter'] = [
    'exclude'                 => true,
    'default'                 => '1',
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_change_layout_filter'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr', 'submitOnChange' => true],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_layout_filter'] = [
    'exclude'                 => true,
    'inputType'               => 'select',
    'default'                 => 'list',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getLayoutOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => false, 'tl_class' => 'clr'],
    'reference'               => &$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType']['options'],
    'sql'                     => "varchar(25) NOT NULL default 'list'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_limit'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "int(10) NOT NULL default 30"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_max_data'] = [
    'exclude'                 => true,
    'default'                 => 0,
    'inputType'               => 'text',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "int(10) NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_render_searchHtml'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_without_tiles'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_without_contact'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_enable_tag_filter'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr', 'submitOnChange' => true],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_enable_type_filter'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_enable_location_filter'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_tag_filter_selection'] = [
    'default'                 => null,
    'exclude'                 => true,
    'inputType'               => 'checkboxWizard',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getTagOptions"],
    'eval'                    => ['multiple' => true, 'tl_class' => 'clr'],
    'sql'                     => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_show_city'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_show_category'] = [
    'exclude'                 => true,
    'default'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_show_image'] = [
    'exclude'                 => true,
    'default'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_show_selfHelpFocus'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

/*
 * Child fields
 */

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_data_mode'] = [
    'exclude'                 => true,
    'default'                 => "0",
    'inputType'               => 'radio',
    'options'                 => ['0', '1', '2', '3', '4', '5'],
    'reference'               => &$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option'],
    'sql'                     => "char(1) NOT NULL default '0'",
    'eval'                    => ['submitOnChange' => true, 'tl_class' => "clr"]
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_filter'] = [
    'exclude'                 => true,
    'default'                 => serialize([]),
    'inputType'               => 'checkbox',
    'options'                 => ['range', 'price'],
    'reference'               => &$GLOBALS['TL_LANG']['tl_module']['gutesio_child_filter_option'],
    'eval'                    => ['tl_class'=>'clr', "multiple" => true],
    'sql'                     => "TEXT NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_search_label'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "varchar(100) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_search_placeholder'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "varchar(200) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_search_description'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "varchar(200) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_text_search'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "varchar(100) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_text_no_results'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "varchar(100) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_showcase_link'] = [
    'exclude'                 => true,
    'inputType'               => 'pageTree',
    'eval'                    => ['fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'],
    'sql'                     => "int(10) unsigned NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_type'] = [
    'exclude'                 => true,
    'default'                 => serialize([]),
    'inputType'               => 'checkbox',
    'options'                 => [
        'product',
        'event',
        'news',
        'exhibition',
        'advertisement',
        'job',
        'arrangement',
        'service',
        'voucher',
        'person'
    ],
    'eval'                    => ['tl_class' => 'clr', 'multiple' => true],
    'sql'                     => "VARCHAR(250) NOT NULL default '".serialize([])."'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_category'] = [
    'exclude'                 => true,
    'default'                 => serialize([]),
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getCategoryOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'                     => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_enable_category_filter'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr', 'submitOnChange' => true],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_category_filter_selection'] = [
    'exclude'                 => true,
    'default'                 => serialize([]),
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getCategoryOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'                     => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_tag'] = [
    'exclude'                 => true,
    'default'                 => serialize([]),
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getTagOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'                     => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_selection'] = [
    'default' => '',
    'exclude' => true,
    'inputType' => 'select',
    'filter' => true,
    'sorting' => true,
    'search' => true,
    'options_callback' => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, 'loadChildOptions'],
    'eval' => [
        'mandatory' => false,
        'multiple' => true,
        'tl_class' => 'clr',
        'chosen' => true,
        'includeBlankOption' => true
    ],
    'sql' => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_sort_by_date'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class' => 'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

//$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_determine_orientation'] = [
//    'exclude'                 => true,
//    'default'                 => false,
//    'inputType'               => 'checkbox',
//    'eval'                    => ['tl_class' => 'clr'],
//    'sql'                     => "char(1) NOT NULL default '0'"
//];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_disable_sorting_filter'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class' => 'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_load_klaro_consent'] = [
    'exclude'                 => true,
    'default'                 => false,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr', 'submitOnChange' => true],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_carousel_template'] = [
    'exclude'                 => true,
    'default'                 => "",
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getCarouselTemplateOptions"],
    'eval'                    => ['includeBlankOption' => true, 'tl_class' => 'clr'],
    'sql'                     => "VARCHAR(250) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cart_no_items_text'] = [
    'exclude'                 => true,
    'default'                 => "",
    'inputType'               => 'textarea',
    'eval'                    => ['tl_class' => 'clr'],
    'sql'                     => "text NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_show_contact_data'] = [
    'exclude'                 => true,
    'default'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cart_page'] = [
    'exclude'                 => true,
    'inputType'               => 'pageTree',
    'eval'                    => ['fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
    'sql'                     => "int(10) unsigned NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_max_childs'] = [
    'exclude'                 => true,
    'default'                 => 0,
    'inputType'               => 'text',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "int(10) NOT NULL default 0"
];
$GLOBALS['TL_DCA']['tl_module']['fields']['lazyBanner'] = [
    'default'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '1'"
];
$GLOBALS['TL_DCA']['tl_module']['fields']['reloadBanner'] = [
    'default'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '1'"
];
$GLOBALS['TL_DCA']['tl_module']['fields']['loadMonth'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'default'                 => '6',
    'eval'                    => ['rgxp'=>'digit', 'tl_class'=>'clr'],
    'sql'                     => "int(10) unsigned NOT NULL default '6'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_check_position'] = [
    'exclude'                 => true,
    'default'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "char(1) NOT NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_show_detail_link'] = [
    'exclude'                 => true,
    'default'                 => 1,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "int NOT NULL default 1"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['limit_detail_offers'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'default'                 => 0,
    'eval'                    => ['rgxp'=>'digit', 'tl_class'=>'clr'],
    'sql'                     => "int(10) unsigned NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_hide_events_without_date'] = [
    'exclude'                 => true,
    'default'                 => 0,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class'=>'clr'],
    'sql'                     => "int NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_folder'] = [
    'exclude'   => true,
    'inputType' => 'fileTree',
    'eval'      => [
        'files' => false,
        'fieldType' => 'checkbox', // allow multi-folder selection
        'isFolder' => true,
        'multiple' => true,
        'tl_class' => 'clr'
    ],
    'sql'       => 'blob NULL'
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_skip_unlinked'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '0',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '0'"
];

// Banner options: fullscreen, hide footer, override footer text
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_fullscreen'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '0',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_hide_poweredby'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '0',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '0'"
];

// Banner option: render media below content as background on portrait screens
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_media_bg_portrait'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '0',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_poweredby_text'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'default'   => '',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "varchar(255) NOT NULL default ''"
];

// Performance/Lazy options for banner
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_lazy_mode'] = [
    'exclude'   => true,
    'inputType' => 'select',
    'default'   => '0',
    'options'   => ['0','1','2'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_lazy_mode_option'],
    'eval'      => ['includeBlankOption' => false, 'tl_class' => 'w50 clr'],
    'sql'       => "char(1) NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_defer_assets'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '1',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_limit_initial'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'default'   => 1,
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'       => 'int(10) unsigned NOT NULL default 1',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_defer_qr'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '1',
    'eval'      => ['tl_class' => 'clr w50'],
    'sql'       => "char(1) NOT NULL default '1'",
];

// Banner option: open links in new tab
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_links_new_tab'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '0',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '0'",
];

// Banner option: also generate QR for image slides when a link exists
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_qr_for_images'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '0',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '0'",
];

// Banner option: strictly hide slides whose images are not locally present nor available on CDN
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_strict_images'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '0',
    'eval'      => ['tl_class' => 'clr w50'],
    'sql'       => "char(1) NOT NULL default '0'",
];

// Generic lists option: strictly hide items whose images are not locally present nor available on CDN
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_strict_images'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => '0',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '0'",
];

// Banner protection: optional GET parameter guard
$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_guard_param'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'default'   => '',
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_guard_param'],
    'eval'      => ['maxlength' => 64, 'tl_class' => 'w50 clr'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_banner_guard_value'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'default'   => '',
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_guard_value'],
    'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];