<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\ResourceLoader;
use con4gis\MapsBundle\Classes\Events\LoadMapdataEvent;
use con4gis\MapsBundle\Resources\contao\models\C4gMapProfilesModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LoadMapDataListener
{
    public function onLoadMapDataDoIt(
        LoadMapdataEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher
    ) {
        $mapData = $event->getMapData();
        $profile = C4gMapProfilesModel::findByPk($mapData['profile']);
        if ($profile && $profile->ownGeosearch) {
            unset($mapData['geosearch']['url']);
        }
        $event->setMapData($mapData);
        ResourceLoader::loadCssResource('/bundles/gutesiooperator/dist/css/c4g_maps.min.css');
    }
}
