<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes\Models;

use Contao\Model;

class GutesioOperatorSettingsModel extends Model
{
    protected static $strTable = 'tl_gutesio_operator_settings';

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
