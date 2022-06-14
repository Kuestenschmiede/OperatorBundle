<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */

namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel;
use gutesio\OperatorBundle\Classes\Curl\CurlGetRequest;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use Exception;

class ServerService
{
    /**
     * @return string
     * @throws Exception
     */
    public function getMainServerURL() : string
    {
        $settings = GutesioOperatorSettingsModel::findSettings();
        if ($settings->mainServerUrl) {
            return $settings->mainServerUrl;
        }
        $mainServerUrl = $this->fetchMainServerURL();
        if ($mainServerUrl !== '') {
            $settings->mainServerUrl = $mainServerUrl;
            $settings->save();
            return $mainServerUrl;
        }
        throw new Exception('Cannot determine main server url.');
    }

    private function fetchMainServerURL() : string
    {
        $settings = C4gSettingsModel::findSettings();
        $request = new CurlGetRequest();
        $baseUrl = $settings->con4gisIoUrl;
        if (!C4GUtils::endsWith($baseUrl, '/')) {
            $baseUrl .= '/';
        }
        $request->setUrl($baseUrl.'mainServerURL.php');
        return $request->send()->getData();
    }
}