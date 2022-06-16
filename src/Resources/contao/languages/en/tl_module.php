<?php
/**
 * con4gis - the gis-kit
 *
 * @version   php 7
 * @package   east_frisia
 * @author    contributors (see "authors.txt")
 * @license   GNU/LGPL http://opensource.org/licenses/lgpl-3.0.html
 * @copyright Küstenschmiede GmbH Software & Design 2011 - 2018
 * @link      https://www.kuestenschmiede.de
 */

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode'] = ['Load mode', 'Determines which showcases are loaded in this module.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode'] = ['Load mode', 'Determines which offers are loaded in this module.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_load_step'] = ['Load steps', 'The number of records per request, if any.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_load_max'] = ['Maximum number of records', 'The maximum number of records, if any. 0 = Unlimited..'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType'] = ['Layout', 'Determines which layout is used for the listing.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_redirect_page'] = ['Redirect page', "If you don't want this module to display details, you can select an alternative detail page here. The alias of the records will be appended to the URL of this detail page."];
$GLOBALS['TL_LANG']['tl_module']['gutesio_showcase_list_page'] = ['Showcase list page', 'Select the page where the showcase list is included (required for redirection).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_offer_list_page'] = ['Offer list page', 'Select the page where the offer list is included (required for redirection).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_details'] = ['Show details', 'Determines whether this module should have a detail view.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_render_searchHtml'] = ['Create HTML for Contao search', 'Determines whether this module should generate the HTML for the Contao search. This should only be active for the main showcase list.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_limit'] = ['Loading steps', 'Determines how many records are loaded in each request (default: 30).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_max_data'] = ['Maximum number of records', 'Determines the maximum number of records displayed in the list (0 means no limit).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_city'] = ['Show location in list', 'Also shows the location in the list.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting'] = ['Initial sort', 'Changes the initial sort (default: random).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_filter'] = ['Activate filter', 'Activates the filter above the list.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_change_layout_filter'] = ['Change layout of the list after filtering', 'Set this checkbox if you want the list to change its layout after a filter input has been made.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layout_filter'] = ['Layout of the list after filtering', 'Determines in which layout the list is displayed after filter input.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_type'] = ['Category', 'Only showcases of this category(ies) are displayed.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_directory'] = ['Directory', 'Only showcases in categories from this directory or these directories are displayed.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_tags'] = ['Tags', 'Only showcases that have one of these tags assigned will be displayed.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_tag_filter'] = ['Tag filter', 'Activates the tag selection above this showcase list.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_tag_filter_selection'] = ['Tag selection', 'Determines the tags that are available in the tag filter.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_child_search_label'] = ['Label of the search field', 'The label of the search box.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_filter'] = ['Offer filter', 'Select which filters should be available above the list.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_search_placeholder'] = ['Placeholder of the search field', 'Placeholder of the search field.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_search_description'] = ['Description of the search field', 'Description of the search field.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_text_search'] = ['Text before search', 'The text output instead of the result list if no search has been performed yet.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_text_no_results'] = ['Text no results', 'The text output instead of the results list if no results were found.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_headline_search_results'] = ['Heading of the result list', 'Headline above the result list, if available.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_headline_recent'] = ['Headline above the list of latest offers', 'Headline above the list of latest offers.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_showcase_link'] = ['Link to the showcases', 'The page where the showcases are included.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_type'] = ['Types to be displayed', 'Only the selected type is displayed.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_category'] = ['Categories to display', 'Only the selected categories are displayed.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_tag'] = ['Tags to be displayed', 'Only offers that have been assigned at least one of these tags will be displayed.'];

$GLOBALS['TL_LANG']['tl_module']['generic_legend'] = 'Generic settings';
$GLOBALS['TL_LANG']['tl_module']['load_legend'] = 'Load settings';
$GLOBALS['TL_LANG']['tl_module']['showcase_filter_legend'] = 'Filter settings';
$GLOBALS['TL_LANG']['tl_module']['showcase_tag_filter_legend'] = 'Tag filter settings';

$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['random'] = 'random';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['name_asc'] = 'ascending';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['name_desc'] = 'descending';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['tstamp_desc'] = 'newest';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['distance'] = 'disctance';

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['0'] = 'Load all showcases';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['1'] = 'Load by categories';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['2'] = 'Load by directories';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['3'] = 'Load by tags';

$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['0'] = 'Load all offers';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['1'] = 'Load by type';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['2'] = 'Load by category';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['3'] = 'Load by tags';

$GLOBALS['TL_LANG']['tl_module']['gutesio_child_filter_option']['price'] = "Price sorting";
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_filter_option']['range'] = "Date filter";

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType']['options']['plain'] = "Minimal styling";
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType']['options']['list'] = "List view";
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType']['options']['grid'] = "Grid view";

$GLOBALS['TL_LANG']['tl_module']['optional_heading_hint'] = "The 'Headline' field is optional.
                    The heading is output between the filter and the list.";
$GLOBALS['TL_LANG']['tl_module']['gutes_heading_hint'] = 'The selection of the h tag (e.g. h3) also decides the respective heading of the list elements.
                    Example: If h3 is selected for the heading,
                    the list elements below it will each have an h4 heading.';