<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Models;

use Contao\Model;

class GutesioOperatorSettingsModel extends Model
{
    protected static $strTable = 'tl_gutesio_operator_settings';

    public static function findSettings()
    {
        global $objPage;

        if ($objPage !== null) {
            $rootPage = \Contao\PageModel::findByPk($objPage->rootId);
            if ($rootPage !== null && $rootPage->rootTitle !== '') {
                $objSettings = static::findOneBy('domaintitle', $rootPage->rootTitle);
                if ($objSettings !== null) {
                    return $objSettings;
                }
            }
        }

        $collSettings = static::findAll();
        if ($collSettings) {
            return $collSettings->current();
        }

        return null;
    }
}
