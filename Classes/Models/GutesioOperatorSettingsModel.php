<?php
/**
 * This file is part of con4gis,
 * the gis-kit for Contao CMS.
 *
 * @package   	con4gis
 * @version        6
 * @author  	    con4gis contributors (see "authors.txt")
 * @license 	    LGPL-3.0-or-later
 * @copyright 	Küstenschmiede GmbH Software & Design
 * @link              https://www.con4gis.org
 *
 */

namespace gutesio\OperatorBundle\Classes\Models;


use Contao\Model;

class GutesioOperatorSettingsModel extends Model
{
    protected static $strTable = "tl_gutesio_operator_settings";
    
    public static function findSettings()
    {
        $collSettings = static::findAll();
        if ($collSettings) {
            foreach ($collSettings as $objSettings) {
                return $objSettings;
            }
        }
        
        return null;
    }
}