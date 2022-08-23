<?php

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

$GLOBALS['TL_CRON']['minutely'][] = [\gutesio\OperatorBundle\Classes\Cron\SyncDataCron::class, 'onMinutely'];
$GLOBALS['TL_CRON']['hourly'][] = [\gutesio\OperatorBundle\Classes\Cron\SendStatisticDataCron::class, 'onHourly'];


/** just needed with Contao 4.9 */
$installedPackages = \Contao\System::getContainer()->getParameter('kernel.packages');
$contaoVersion = $installedPackages['contao/core-bundle'];
if ($contaoVersion && strpos($contaoVersion, '4.9')) {
    $GLOBALS['TL_HOOKS']['getSearchablePages'][] = [\gutesio\OperatorBundle\Controller\ShowcaseListModuleController::class, 'onGetSearchablePages'];
    $GLOBALS['TL_HOOKS']['getSearchablePages'][] = [\gutesio\OperatorBundle\Controller\OfferListModuleController::class, 'onGetSearchablePages'];
}
