<?php
/*
 * This file is part of con4gis,
 * the gis-kit for Contao CMS.
 *
 * @package    con4gis
 * @version    7
 * @author     con4gis contributors (see "authors.txt")
 * @license    LGPL-3.0-or-later
 * @copyright  Küstenschmiede GmbH Software & Design
 * @link       https://www.con4gis.org
 */
namespace gutesio\OperatorBundle\Classes\Cache;

use con4gis\CoreBundle\Classes\C4GApiCache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShowcaseListApiCache
{
    
    /**
     * @var FilesystemAdapter
     */
    protected $cacheInstance;
    
    /**
     * @var ShowcaseListApiCache
     */
    protected static $instance = null;

    public static function getInstance($cacheDir)
    {
        if (!static::$instance) {
            static::$instance = new self($cacheDir);
        }

        return static::$instance;
    }

    /**
     * C4GLayerApiCache constructor.
     */
    protected function __construct($cacheDir)
    {
        $this->cacheInstance = new FilesystemAdapter(
            $namespace = 'gutesio_showcaseList',
            $defaultLifetime = 0,
            $directory = $cacheDir
        );
    }
    
    public function hasCacheData($cacheChecksum)
    {
        return $this->cacheInstance->hasItem($cacheChecksum);
    }
    
    public function getCacheData($strChecksum)
    {
//        $strChecksum = $this->getCacheKey($strApiEndpoint, $arrFragments);
        if ($this->hasCacheData($strChecksum)) {
            return $this->cacheInstance->getItem($strChecksum)->get();
        }
        
        return false;
    }
    
    private function saveCacheData($strChecksum, $strContent)
    {
        $cacheData = $this->cacheInstance->getItem($strChecksum);
        $cacheData->set($strContent);
        
        return $this->cacheInstance->save($cacheData);
    }
    
    public function putCacheData($strChecksum, $strContent)
    {
//        $strChecksum = $this->getCacheKey($strApiEndpoint, $arrFragments);
        $this->saveCacheData($strChecksum, $strContent);
    }
}
