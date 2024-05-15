<?php

$GLOBALS['TL_DCA']['tl_news']['fields']['gutesUuid'] = [
    'sorting'                 => true,
    'default'                 => '',
    'eval'                    => ['tl_class'=>'w50', 'maxlength'=>255],
    'sql'                     => "varchar(255) NOT NULL default ''",
    'exclude'                 => true
];

