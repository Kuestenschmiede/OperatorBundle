<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Cache;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

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

    public function clearCache()
    {
        $this->cacheInstance->clear();
    }
}
