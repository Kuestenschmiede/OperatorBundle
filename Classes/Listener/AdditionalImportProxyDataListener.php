<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\Events\AdditionalImportProxyDataEvent;
use Contao\Database;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Contao\System;

class AdditionalImportProxyDataListener
{
    public function importProxyData(AdditionalImportProxyDataEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        $db = Database::getInstance();

        $installedPackages = System::getContainer()->getParameter('kernel.packages');
        $operatorVersion = $installedPackages['gutesio/operator'];
        $dataModelVersion = $installedPackages['gutesio/data-model'];

        $offerStatistic = $db->prepare('SELECT * FROM tl_gutesio_offer_statistic')
            ->execute()->fetchAllAssoc();
        $showcaseStatistic = $db->prepare('SELECT * FROM tl_gutesio_showcase_statistic')
            ->execute()->fetchAllAssoc();

        $proxyData = [
            [
                'proxyKey' => 'operatorVersion',
                'proxyData' => $operatorVersion,
            ],
            [
                'proxyKey' => 'dataModelVersion',
                'proxyData' => $dataModelVersion,
            ],
        ];

        if ($offerStatistic) {
            foreach ($offerStatistic as $statisticEntry) {
                $proxyData[] = [
                    'proxyKey' => 'offerStatistic_' . $statisticEntry['id'],
                    'proxyData' => $statisticEntry['uuid'] . ',' . $statisticEntry['date'] . ',' . $statisticEntry['offerId'] . ',' . $statisticEntry['visits'] . ',' . $statisticEntry['ownerId'],
                ];
            }
        }
        if ($showcaseStatistic) {
            foreach ($showcaseStatistic as $statisticEntry) {
                $proxyData[] = [
                    'proxyKey' => 'showcaseStatistic_' . $statisticEntry['id'],
                    'proxyData' => $statisticEntry['uuid'] . ',' . $statisticEntry['date'] . ',' . $statisticEntry['offerId'] . ',' . $statisticEntry['visits'] . ',' . $statisticEntry['ownerId'],
                ];
            }
        }

        $event->setProxyData($proxyData);
    }
}
