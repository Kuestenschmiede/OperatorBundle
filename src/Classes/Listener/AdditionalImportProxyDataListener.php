<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\Events\AdditionalImportProxyDataEvent;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Contao\System;

class AdditionalImportProxyDataListener
{
    public function importProxyData(AdditionalImportProxyDataEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        $installedPackages = System::getContainer()->getParameter('kernel.packages');
        $operatorVersion = $installedPackages['gutesio/operator'];
        $dataModelVersion = $installedPackages['gutesio/data-model'];

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

        $event->setProxyData($proxyData);
    }
}
