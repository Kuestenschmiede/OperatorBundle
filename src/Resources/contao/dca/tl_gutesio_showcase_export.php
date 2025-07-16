<?php
/*
 * This file is part of con4gis, the gis-kit for Contao CMS.
 * @package con4gis
 * @version 10
 * @author con4gis contributors (see "authors.txt")
 * @license LGPL-3.0-or-later
 * @copyright (c) 2010-2025, by Küstenschmiede GmbH Software & Design
 * @link https://www.con4gis.org
 */


/**
 * Table tl_gutesio_showcase_export
 */

use Contao\DC_Table;

$strName = 'tl_gutesio_showcase_export';

$GLOBALS['TL_DCA'][$strName] = array
(
    //config
    'config' => array
    (
        'dataContainer'     => DC_Table::class,
        'enableVersioning'  => true,
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ],
        ],
    ),

    //List
    'list' => array
    (
        'sorting' => array
        (
            'mode'              => 2,
            'fields'            => array('name ASC'),
            'panelLayout'       => 'filter;sort,search,limit',
            'headerFields'      => array('name'),
            'icon'              => 'bundles/con4giscore/images/be-icons/con4gis_blue.svg',
        ),

        'label' => array
        (
            'fields'            => array('name'),
            'showColumns'       => true,
        ),

        'global_operations' => array
        (
            'all' => [
                'label'         => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'          => 'act=select',
                'class'         => 'header_edit_all',
                'attributes'    => 'onclick="Backend.getScrollOffSet()" accesskey="e"'
            ],
            'back' => [
                'href'                => 'key=back',
                'class'               => 'header_back',
                'button_callback'     => ['\con4gis\CoreBundle\Classes\Helper\DcaHelper', 'back'],
                'icon'                => 'back.svg',
                'label'               => &$GLOBALS['TL_LANG']['MSC']['backBT'],
            ],
        ),

        'operations' => array
        (
            'edit' => array
            (
                'label'         => &$GLOBALS['TL_LANG'][$strName]['edit'],
                'href'          => 'act=edit',
                'icon'          => 'edit.svg',
            ),
            'copy' => array
            (
                'label'         => &$GLOBALS['TL_LANG'][$strName]['copy'],
                'href'          => 'act=copy',
                'icon'          => 'copy.svg',
            ),
            'delete' => array
            (
                'label'         => &$GLOBALS['TL_LANG'][$strName]['delete'],
                'href'          => 'act=delete',
                'icon'          => 'delete.svg',
                'attributes'    => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\')) return false;Backend.getScrollOffset()"',
            ),
            'show' => array
            (
                'label'         => &$GLOBALS['TL_LANG'][$strName]['show'],
                'href'          => 'act=show',
                'icon'          => 'show.svg',
            ),
            'export' => [
                'label' => &$GLOBALS['TL_LANG']['tl_gutesio_showcase_export']['create_export'],
                'href' => "",
                'icon' => "",
                'attributes' => "",
                'button_callback' => [\gutesio\OperatorBundle\Classes\Callback\GutesioShowcaseExportCallback::class, "getExportButton"],
                'route' => "create_showcase_export"
            ]
        )
    ),

    //Palettes
    'palettes' => array
    (
        'default'   =>  '{data_legend},name,types;'
    ),

    //Fields
    'fields' => array
    (
        'id' => [
            'sql'                     => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp' => [
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ],
        'name' => [
            'label'             => &$GLOBALS['TL_LANG'][$strName]['name'],
            'default'           => '',
            'inputType'         => 'text',
            'eval'              => ['mandatory' => true, 'tl_class' => 'long'],
            'exclude'           => true,
            'sql'               => "varchar(255) default ''"
        ],

        'types' => [
            'label'             => &$GLOBALS['TL_LANG'][$strName]['updateViaCache'],
            'default'           => [],
            'inputType'         => 'select',
            'options_callback'  => [\gutesio\OperatorBundle\Classes\Callback\GutesioShowcaseExportCallback::class, 'getTypeOptions'],
            'eval'              => ['mandatory' => true, 'tl_class' => 'long', 'includeBlankOption' => false, 'multiple' => true, 'chosen' => true],
            'exclude'           => true,
            'sql'               => "blob NULL"
        ],

        'translateFieldNames' => [
            'default' => 0,
            'inputType' => 'checkbox',
            'sql' => "tinyint(1) NOT NULL default 0"
        ]


    )
);

