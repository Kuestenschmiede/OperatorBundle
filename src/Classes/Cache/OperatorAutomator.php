<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
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

    /**
     * Purge the gutesio cache for offer data.
     */
    public function purgeOfferDataCache()
    {
        OfferDataCache::getInstance()->clearCache();
    }
}
