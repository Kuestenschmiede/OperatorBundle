<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Cache;

use Contao\FrontendUser;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class OfferDataCache
{
    /**
     * @var FilesystemAdapter
     */
    protected $cacheInstance;

    /**
     * @var OfferDataCache
     */
    protected static $instance = null;

    public static function getInstance($cacheDir = '../var/cache/prod/con4gis')
    {
        if (!static::$instance) {
            static::$instance = new self($cacheDir);
        }

        return static::$instance;
    }

    /**
     * OfferTagDataCache constructor.
     */
    protected function __construct($cacheDir)
    {
        $this->cacheInstance = new FilesystemAdapter(
            $namespace = 'gutesio_offerData',
            $defaultLifetime = 0,
            $directory = $cacheDir
        );
    }

    public function hasCacheData(string $strChecksum): bool
    {
        return $this->cacheInstance->hasItem($strChecksum);
    }

    public function getCacheData(string $strChecksum)
    {
        if ($this->hasCacheData($strChecksum)) {
            return $this->cacheInstance->getItem($strChecksum)->get();
        }

        return false;
    }

    private function saveCacheData($strChecksum, $strContent): bool
    {
        $cacheData = $this->cacheInstance->getItem($strChecksum);
        $cacheData->set($strContent);

        return $this->cacheInstance->save($cacheData);
    }

    public function putCacheData(string $strChecksum, array $content): void
    {
        $strContent = serialize($content);
        $this->saveCacheData($strChecksum, $strContent);
    }

    public function clearCache(): void
    {
        $this->cacheInstance->clear();
    }
}
