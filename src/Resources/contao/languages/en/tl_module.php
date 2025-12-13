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
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_category'] = ['Show categories in list', 'Also shows the categories in the list.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_image'] = ['Show image in list', 'Also shows image in the list.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_selfHelpFocus'] = ['Show self help focus in list', 'Also shows self help focus in the list.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting'] = ['Initial sort', 'Changes the initial sort (default: random).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_filter'] = ['Activate filter', 'Activates the filter above the list.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_ext_filter'] = ['Activate extended filter button', 'Activates the filter button above the list.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_change_layout_filter'] = ['Change layout of the list after filtering', 'Set this checkbox if you want the list to change its layout after a filter input has been made.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layout_filter'] = ['Layout of the list after filtering', 'Determines in which layout the list is displayed after filter input.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_type'] = ['Category', 'Only showcases of this category(ies) are displayed.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_directory'] = ['Directory', 'Only showcases in categories from this directory or these directories are displayed.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_tags'] = ['Tags', 'Only showcases that have one of these tags assigned will be displayed.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_tag_filter'] = ['Tag filter', 'Activates the tag selection above this showcase list.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_tag_filter_selection'] = ['Tag selection', 'Determines the tags that are available in the tag filter.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_elements'] = ['Restrict showcases', 'Only the selected showcases are loaded. Default: all.'];

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
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_sort_by_date'] = ['Sort initially by date', 'Sorts the contents initially by their start date. Attention: Only taken into account if only events are displayed in the list.'];
//$GLOBALS['TL_LANG']['tl_module']['gutesio_child_determine_orientation'] = ['Determine orientation', 'The orientation of the list screens should be determined. Attention! Time-consuming.'];

$GLOBALS['TL_LANG']['tl_module']['cart_payment_url'] = ['Link to payment page', 'The page where the payment process is performed.'];
$GLOBALS['TL_LANG']['tl_module']['cart_no_items_text'] = ['Text when cart is empty', 'This text will be displayed when the cart is empty.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_show_contact_data'] = ['Show contact data', 'If the checkbox is set, then contact data will be shown in the watchlist.'];
$GLOBALS['TL_LANG']['tl_module']['cart_page'] = ['Shopping cart page', 'Page where the shopping cart module is located.'];

$GLOBALS['TL_LANG']['tl_module']['generic_legend'] = 'Generic settings';
$GLOBALS['TL_LANG']['tl_module']['load_legend'] = 'Load settings';
$GLOBALS['TL_LANG']['tl_module']['showcase_filter_legend'] = 'Filter settings';
$GLOBALS['TL_LANG']['tl_module']['showcase_tag_filter_legend'] = 'Tag filter settings';
$GLOBALS['TL_LANG']['tl_module']['cart_legend'] = 'Cart settings';
$GLOBALS['TL_LANG']['tl_module']['appearance_legend'] = 'Appearence settings';
$GLOBALS['TL_LANG']['tl_module']['performance_legend'] = 'Performance settings';

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_theme_color'] = [
    'Banner theme color',
    'Primary accent color for overlays, the ad label and the powered-by bar (hex, e.g. #2ea1db).'];

// Banner: play videos
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_play_videos'] = [
    'Play videos',
    'Plays videos in the banner. Considers MP4 files from the selected folders as well as video links (field "videoLink") of the children. If a video is available, an additional video slide is inserted (in addition to the image slide).'
];

// Banner: mute videos
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_mute_videos'] = [
    'Mute videos',
    'Mutes all videos (MP4 and YouTube) by default. Recommended for kiosk/advertising screens.'
];

// Banner: Event overlay on videos
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_show_event_overlay'] = [
    'Show event overlay on videos',
    'Displays a subtle overlay on event video slides showing "Location, Date Time". Applies to YouTube and MP4 videos of events.'
];

// Banner: render images full cover
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_media_bg_full'] = [
    'Render images full cover',
    'Always render photos/images as full-cover background (object-fit: cover). Default: enabled.'
];

// Banner: overlay transparency
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_overlay_opacity'] = [
    'Overlay transparency (%)',
    'Controls the opacity of overlays (0–100). 0 = default (template fallback). Typical values: 45–80.'
];

// Banner: hide footer on video slides
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_hide_footer_on_videos'] = [
    'Hide footer on videos',
    'Automatically hides the footer including QR code when a video slide is active.'
];

// Banner: maximum playback time for videos
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_video_timeout'] = [
    'Max. playback time for videos (seconds)',
    'Defines how long videos are played at most before switching to the next slide. 0 = full video length. Default: 180 seconds.'
];

// Banner: strict image mode
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_strict_images'] = [
    'Enable strict image mode',
    'Hides slides with missing images. Only shows images that are locally available or quickly confirmed by the CDN.'
];

// Lists: strict image mode (Showcase/Offer Lists)
$GLOBALS['TL_LANG']['tl_module']['gutesio_strict_images'] = [
    'Enable strict image mode for lists',
    'Hides list items without available images. Only images that are locally available or quickly confirmed by the CDN are shown.'
];

// Banner protection (optional via GET parameter)
$GLOBALS['TL_LANG']['tl_module']['security_legend'] = 'Access protection';
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_guard_param'] = [
    'Guard parameter (GET)',
    'Optional name of a GET parameter (e.g. "bannerKey"). If set, the banner only loads when the request contains this parameter.'
];
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_guard_value'] = [
    'Expected parameter value',
    'Optional expected value for the guard parameter. Leave empty if you only want to check for presence.'
];

// Banner: open links in a new tab
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_links_new_tab'] = [
    'Open links in a new tab',
    'Opens banner links in a new browser tab (target="_blank").'
];

// Banner: configurable slide interval
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_interval'] = [
    'Slide change interval (ms)',
    'Time between two slides in milliseconds. Default: 15000.'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['random'] = 'random';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['name_asc'] = 'ascending';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['name_desc'] = 'descending';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['tstamp_desc'] = 'newest';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['distance'] = 'disctance';

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['0'] = 'Load all showcases';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['1'] = 'Load by categories';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['2'] = 'Load by directories';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['3'] = 'Load by tags';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['4'] = 'Exclude categories';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['5'] = 'Select showcases';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['6'] = 'Do not load showcases';

$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['0'] = 'Load all offers';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['1'] = 'Load by type';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['2'] = 'Load by category';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['3'] = 'Load by tags';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['4'] = 'Select offers';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['5'] = 'Do not load offers';

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

$GLOBALS['TL_LANG']['tl_module']['gutesio_max_childs'] = ['Maximum number of content per showcase','You can limit the number of offers per showcase (default: 0 = unlimited).'];
$GLOBALS['TL_LANG']['tl_module']['lazyBanner'] = ['Lazy load images','Lazy load images instead of loading all at once.'];
$GLOBALS['TL_LANG']['tl_module']['reloadBanner'] = ['Automatically reload banner','Automatically reload banner every hour.'];

//new translate!
$GLOBALS['TL_LANG']['tl_module']['gutesio_disable_sorting_filter'] = ['Sortierfilter ausblenden', 'Blendet die Sortiermöglichkeiten aus. sehr Sinnvoll bei einer Veranstaltungsliste.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_without_tiles'] = ['Kacheln unterhalb ausblenden','Alle Kacheln unterhalb der Details werden ausgeblendet.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_without_contact'] = ['Kontaktdaten ausblenden','Alle Kontaktdaten werden ausgeblendet.'];

// Banner module: mix images from folder
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_folder'] = [
    'Images folders',
    'Choose one or more folders. Images from these folders are mixed randomly into the slider. The link defined in the file metadata is used as the image link.'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_skip_unlinked'] = [
    'Skip images without link',
    'If enabled, images that do not have a link set in their file metadata will be skipped.'
];

// Banner module: rendering & footer options
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_fullscreen'] = [
    'Force fullscreen',
    'Display images full screen (100% of the viewport). Images are centered with cropping (object-fit: cover).'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_hide_poweredby'] = [
    'Hide powered-by footer',
    'If enabled, the “Powered by” footer will not be displayed.'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_poweredby_text'] = [
    'Powered-by text',
    'Optional text for the footer (default: "Powered by").'
];

// Footer alignment (contact + logo left-aligned)
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_footer_align_left'] = [
    'Footer left-aligned (contact + logo)',
    'If enabled, the “Offered by …” line and the logo in the footer are left-aligned. The QR code stays on the right when space allows, or wraps below.'
];

// Banner: Ad label "Anzeige"
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_show_ad_label'] = [
    'Show ad label ("Anzeige")',
    'Displays a small “Anzeige” label in the top-left of the banner to clearly mark paid/sponsored content.'
];

// Media as background on portrait
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_media_bg_portrait'] = [
    'Render media as background on portrait',
    'Displays images/videos behind the content on mobile portrait (no cropping). Overlay/QR/Logo/footer stay on top. The slide remains clickable.'
];

// Performance / Lazy-Loading options
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_lazy_mode'] = [
    'Lazy-loading mode',
    'Controls how slides are initially rendered and how media is lazy-loaded (improves SEO/LCP on websites).'
];
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_lazy_mode_option']['0'] = 'Off (compatibility mode)';
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_lazy_mode_option']['1'] = 'Native lazy (all slides, images/video lazy)';
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_lazy_mode_option']['2'] = 'SEO static-first (only first slide, load the rest later)';

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_defer_assets'] = [
    'Defer slider assets',
    'Loads Tiny‑Slider scripts/styles only when the module enters the viewport.'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_limit_initial'] = [
    'Number of initial slides',
    'How many slides are initially rendered server-side (SEO static-first only, default: 1).'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_defer_qr'] = [
    'Skip QR codes for deferred slides',
    'Reduces initial cost by adding QR codes only for initially rendered slides (deferred slides hydrate later).'
];

// Banner: option to hide event end time (show only start time)
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_hide_event_endtime'] = [
    'Hide end time for events',
    'If enabled, event slides show only the start time (no end time).'
];