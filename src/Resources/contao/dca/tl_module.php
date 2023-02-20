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
    'gutesio_data_max_data,gutesio_data_mode,gutesio_data_restrict_postals,gutesio_data_show_city;' .
    '{showcase_filter_legend},gutesio_initial_sorting,gutesio_enable_filter,gutesio_data_change_layout_filter,gutesio_enable_tag_filter,gutesio_enable_type_filter;';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_data_mode';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_0'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_1'] = 'gutesio_data_type';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_2'] = 'gutesio_data_directory';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_3'] = 'gutesio_data_tags';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_mode_4'] = 'gutesio_data_blocked_types';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_enable_tag_filter';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_enable_tag_filter'] = 'gutesio_tag_filter_selection';

$GLOBALS['TL_DCA']['tl_module']['palettes']['showcase_detail_module'] =
    '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_render_searchHtml,gutesio_showcase_list_page,gutesio_load_klaro_consent;'.
    '{cart_legend},cart_page'
;

$GLOBALS['TL_DCA']['tl_module']['palettes']['offer_list_module'] =
    '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_layoutType,gutesio_child_showcase_link,gutesio_data_render_searchHtml;'.
    '{load_legend},gutesio_child_data_mode,gutesio_data_limit,gutesio_data_max_data,gutesio_child_sort_by_date;'.
    '{cart_legend},cart_page;'.
    '{showcase_filter_legend},gutesio_enable_filter,gutesio_child_search_label,gutesio_child_search_placeholder,'.
    'gutesio_child_search_description,gutesio_child_text_search,gutesio_child_text_no_results,'.
    'gutesio_child_filter,gutesio_data_change_layout_filter;'.
    '{showcase_tag_filter_legend},gutesio_enable_tag_filter;';

$GLOBALS['TL_DCA']['tl_module']['palettes']['offer_detail_module'] =
    '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_render_searchHtml,gutesio_offer_list_page;{cart_legend},cart_page;'
;

$GLOBALS['TL_DCA']['tl_module']['palettes']['cart_module'] =
    '{title_legend},name,headline,type,cart_no_items_text;'
;

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_child_data_mode';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_child_data_mode_0'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_child_data_mode_1'] = 'gutesio_child_type';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_child_data_mode_2'] = 'gutesio_child_category';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_child_data_mode_3'] = 'gutesio_child_tag';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_enable_tag_filter';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'gutesio_data_change_layout_filter';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_enable_tag_filter'] = 'gutesio_tag_filter_selection';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['gutesio_data_change_layout_filter'] = 'gutesio_data_layout_filter';

$GLOBALS['TL_DCA']['tl_module']['palettes']['showcase_carousel_module'] = '{title_legend},name,headline,type;'.
    '{generic_legend},gutesio_data_redirect_page,gutesio_data_max_data,gutesio_data_mode,gutesio_data_restrict_postals,gutesio_carousel_template;';

$GLOBALS['TL_DCA']['tl_module']['palettes']['wishlist_module'] = '{title_legend},name,headline,type,gutesio_show_contact_data;{cart_legend},cart_page;';

$GLOBALS['TL_DCA']['tl_module']['palettes']['banner_module'] = '{title_legend},name,headline,type;';

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_mode'] = [
    'exclude'                 => true,
    'default'                 => "0",
    'inputType'               => 'radio',
    'options'                 => ['0', '1', '2', '3', '4'],
    'reference'               => &$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option'],
    'sql'                     => "char(1) NOT NULL default '0'",
    'eval'                    => ['submitOnChange' => true, 'tl_class' => "clr"]
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_data_type'] = [
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
    'default'                 => 'classic',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getLayoutOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => false, 'tl_class' => 'clr'],
    'reference'               => &$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType']['options'],
    'sql'                     => "varchar(25) NOT NULL default 'classic"
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
    'default'                 => true,
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
    'options'                 => ['0', '1', '2', '3'],
    'reference'               => &$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option'],
    'sql'                     => "char(1) NOT NULL default '1'",
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

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_tag'] = [
    'exclude'                 => true,
    'default'                 => serialize([]),
    'inputType'               => 'select',
    'options_callback'        => [\gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback::class, "getTagOptions"],
    'eval'                    => ['includeBlankOption' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'                     => "text NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['gutesio_child_sort_by_date'] = [
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