<?php

use gutesio\OperatorBundle\Classes\Cache\OperatorAutomator;

$GLOBALS['gutesio']['api-caching'] = ["showcaseList"];

$modules = [
    'gutesio_operator_settings' => [
        'tables' => ['tl_gutesio_operator_settings']
    ]
];

if (!empty($GLOBALS['BE_MOD']['gutesio'])) {
    $GLOBALS['BE_MOD']['gutesio'] = array_merge($GLOBALS['BE_MOD']['gutesio'], $modules);
} else {
    $GLOBALS['BE_MOD']['gutesio'] = $modules;
}

$GLOBALS['TL_MODELS']['tl_gutesio_operator_settings'] = \gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel::class;

$GLOBALS['TL_PURGE']['folders']['gutesio_offer_data'] = [
    'callback' => [OperatorAutomator::class, 'purgeOfferDataCache'],
    'affected' => ['var/cache/prod/con4gis/gutesio_offerData']
];