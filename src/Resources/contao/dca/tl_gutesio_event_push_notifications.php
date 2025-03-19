<?php

$GLOBALS['TL_DCA']['tl_gutesio_event_push_notifications'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'enableVersioning' => false,
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

    ],

    'fields' => [
        'id' => [
            'sql' => 'int unsigned NOT NULL auto_increment'
        ],

        'identifier' => [
            'sql' => "varchar(255) NOT NULL default ''"
        ],

        'sentTime' => [
            'sql' => 'int unsigned NOT NULL default 0'
        ]
    ],
];