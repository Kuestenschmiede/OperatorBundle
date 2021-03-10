<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
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
        $result = Database::getInstance()->prepare('SELECT id FROM tl_gutesio_operator_settings')
            ->execute()->fetchAllAssoc();

        if (!Input::get('act')) {
            if (sizeof($result) === 0) {
                $this->redirect($this->addToUrl('act=create'));
            } else {
                $this->redirect($this->addToUrl('act=edit&id=' . $result[0]['id']));
            }
        }
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
