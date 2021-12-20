<?php
/**
 * This file is part of con4gis,
 * the gis-kit for Contao CMS.
 *
 * @package   	con4gis
 * @version    7
 * @author  	    con4gis contributors (see "authors.txt")
 * @license 	    LGPL-3.0-or-later
 * @copyright 	Küstenschmiede GmbH Software & Design
 * @link              https://www.con4gis.org
 *
 */

use gutesio\OperatorBundle\Classes\Callback\MapsCallback;

$cbClass = MapsCallback::class;

//ToDo map implementation
$GLOBALS['c4g_locationtypes'][] = 'gutes';
$GLOBALS['c4g_locationtypes'][] = 'gutesElem';
$GLOBALS['c4g_locationtypes'][] = 'gutesPart';


$GLOBALS['TL_DCA']['tl_c4g_maps']['palettes']['gutes'] = "{general_legend},name,location_type,types;{location_legend},aliasSite,initial_opened,tDontShowIfEmpty,data_layername,data_hidelayer,hide_when_in_tab,exemptFromFilter,exemptFromRealFilter,hideInStarboard;{protection_legend:hide},protect_element;{expert_legend:hide},excludeFromSingleLayer,be_optimize_checkboxes_limit;";
$GLOBALS['TL_DCA']['tl_c4g_maps']['palettes']['gutesElem'] = "{general_legend},name,location_type;{location_legend},aliasSite,initial_opened,tDontShowIfEmpty,data_layername,data_hidelayer,locstyle,areaLocstyle,hide_when_in_tab,exemptFromFilter,exemptFromRealFilter,hideInStarboard;{protection_legend:hide},protect_element;{expert_legend:hide},excludeFromSingleLayer,be_optimize_checkboxes_limit;";
$GLOBALS['TL_DCA']['tl_c4g_maps']['palettes']['gutesPart'] = "{general_legend},name,location_type;{location_legend},aliasSite,initial_opened,tDontShowIfEmpty,data_layername,data_hidelayer,locstyle,areaLocstyle,hide_when_in_tab,exemptFromFilter,exemptFromRealFilter,hideInStarboard;{protection_legend:hide},protect_element;{expert_legend:hide},excludeFromSingleLayer,be_optimize_checkboxes_limit;";

$GLOBALS['TL_DCA']['tl_c4g_maps']['fields']['aliasSite'] =
    [
        'label'                   => &$GLOBALS['TL_LANG']['tl_c4g_maps']['aliasSite'],
        'exclude'                 => true,
        'inputType'               => 'pageTree',
        'eval'                    => ['fieldType'=>'radio'],
        'sql'                     => "int(10) unsigned NOT NULL default '0'"
    ];
$GLOBALS['TL_DCA']['tl_c4g_maps']['fields']['directories'] =
    [
        'exclude' => true,
        'inputType' => 'select',
        'options_callback' => [$cbClass, 'getConfiguredDirectories'],
        'eval' => [
            'multiple' => true,
            'tl_class' => 'clr',
            'chosen' => true,
        ],
        'sql' => "blob NULL",
    ];
$GLOBALS['TL_DCA']['tl_c4g_maps']['fields']['types'] =
    [
        'exclude' => true,
        'inputType' => 'select',
        'options_callback' => [$cbClass, 'getConfiguredTypes'],
        'eval' => [
            'multiple' => true,
            'tl_class' => 'clr',
            'chosen' => true,
        ],
        'sql' => "blob NULL",
    ];
$GLOBALS['TL_DCA']['tl_c4g_maps']['fields']['skipTypes'] =
    [
        'exclude'                 => true,
        'default'                 => '',
        'inputType'               => 'checkbox',
        'sql'                     => "char(1) NOT NULL default ''"
    ];

$GLOBALS['TL_DCA']['tl_c4g_maps']['fields']['areaLocstyle'] =
    [
        'exclude'                 => true,
        'inputType'               => 'select',
        'options_callback'        => [$cbClass,'getLocStyles'],
        'eval'                    => ['tl_class'=>'clr', 'chosen' => true],
        'sql'                     => "int(10) unsigned NOT NULL default '0'"
    ];