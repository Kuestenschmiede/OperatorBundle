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
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
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
        try {
            $mainServerUrl = $this->fetchMainServerURL();
        } catch (\Throwable $exception) {
            C4gLogModel::addLogEntry("operator", $exception->getMessage());
            return "";
        }

        if ($mainServerUrl !== '') {
            $settings->mainServerUrl = $mainServerUrl;
            $settings->save();
            return $mainServerUrl;
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    private function fetchMainServerURL() : string
    {
        $settings = C4gSettingsModel::findSettings();
        $request = new CurlGetRequest();
        $baseUrl = $settings->con4gisIoUrl;
        if (!C4GUtils::endsWith($baseUrl, '/')) {
            $baseUrl .= '/';
        }
        $request->setUrl($baseUrl.'mainServerURL.php');
        $response = $request->send();
        if ($response->getStatusCode() === 200 || $response->getStatusCode() === '200') {
            return $response->getData();
        } else {
            throw new Exception('Proxy Server returned response other than 200.');
        }
    }
}