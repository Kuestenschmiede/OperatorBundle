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

use con4gis\CoreBundle\Classes\C4GAutomator;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use Contao\System;
use gutesio\OperatorBundle\Classes\Cache\ShowcaseListApiCache;

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
