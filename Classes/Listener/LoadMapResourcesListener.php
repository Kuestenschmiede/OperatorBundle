<?php


namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\ResourceLoader;
use con4gis\MapsBundle\Classes\Events\LoadMapResourcesEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class LoadMapResourcesListener
{
    public function onLoadMapResourcesLoadOperatorFiles(
        LoadMapResourcesEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher
    ) {
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/js/c4g_all.js");
        $jsCode = "jQuery(document).ready(function(){if (reactRenderReady){reactRenderReady();}});";
        ResourceLoader::loadJavaScriptResourceTag($jsCode, ResourceLoader::BODY);
    }
}