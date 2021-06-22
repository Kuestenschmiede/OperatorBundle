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
    
    }
}
