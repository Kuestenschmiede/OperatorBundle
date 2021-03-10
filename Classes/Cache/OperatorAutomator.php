<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes\Cache;

use con4gis\CoreBundle\Classes\C4GAutomator;
use Contao\System;

class OperatorAutomator extends C4GAutomator
{
    /**
     * Purge the gutesio cache for the showcaseList.
     */
    public function purgeShowcaseListCache()
    {
        ShowcaseListApiCache::getInstance(System::getContainer())->clearCache();
    }
}
