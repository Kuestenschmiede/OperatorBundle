<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Callback;

use con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel;
use Contao\Backend;
use Contao\Database;
use Contao\DataContainer;
use Contao\Input;

class GutesioOperatorSettingCallback extends Backend
{
    public function redirectToDetails()
    {
        $result = Database::getInstance()->prepare('SELECT id FROM tl_gutesio_operator_settings')->execute();

        if (Input::get('key')) return;

        if(!$result->numRows && !Input::get('act'))
        {
            $this->redirect($this->addToUrl('act=create'));
        } else if (!Input::get('id') && !Input::get('act')) {
            $GLOBALS['TL_DCA']['tl_gutesio_operator_settings']['config']['notCreatable'] = true;
            $this->redirect($this->addToUrl('act=edit&id='.$result->id));
        }

        return $result;
    }

    public function deleteMainServerUrl()
    {
        $database = Database::getInstance();
        $statement = $database->prepare("UPDATE tl_gutesio_operator_settings SET mainServerUrl = ''");
        $statement->execute();
    }

    public function loadIoUrl()
    {
        $settings = C4gSettingsModel::findAll();
        if ($settings && $settings[0]) {
            return $settings[0]->con4gisIoUrl;
        }
    }

    public function loadIoKey()
    {
        $settings = C4gSettingsModel::findAll();
        if ($settings && $settings[0]) {
            return $settings[0]->con4gisIoKey;
        }
    }

    public function saveIoUrl($value, DataContainer $dc)
    {
        if ($value) {
            Database::getInstance()->prepare('UPDATE tl_c4g_settings SET con4gisIoUrl = ?')
                ->execute($value);

            return;
        }

        return null;
    }

    public function saveIoKey($value, DataContainer $dc)
    {
        if ($value) {
            Database::getInstance()->prepare('UPDATE tl_c4g_settings SET con4gisIoKey = ?')
                ->execute($value);
        }

        return null;
    }
}
