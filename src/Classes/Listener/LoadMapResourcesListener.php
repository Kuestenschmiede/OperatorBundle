<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\ResourceLoader;
use con4gis\MapsBundle\Classes\Events\LoadMapResourcesEvent;
use Contao\System;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class LoadMapResourcesListener
{
    public function onLoadMapResourcesLoadOperatorFiles(
        LoadMapResourcesEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher
    ) {
        ResourceLoader::loadJavaScriptResource('/bundles/gutesiooperator/dist/js/badge_map.js|static',ResourceLoader::JAVASCRIPT, 'badge_map');
    }
}
