<?php

namespace gutesio\OperatorBundle\Classes\Helper;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\C4GVersionProvider;
use Contao\PageModel;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

class OfferDataHelper
{
    private $settings = null;

    /**
     * @var FileUtils|null
     */
    private $fileUtils = null;

    /**
     */
    public function __construct()
    {
        $this->fileUtils = new FileUtils();
    }

    public function setImageAndDetailLinks($offerData)
    {
        $this->getSettings();

        $offerData['elementLink'] = $this->getElementLink($offerData);

        $childPage = PageModel::findByPk($this->settings->eventDetailPage);

        if ($childPage !== null) {
            $cleanUuid = strtolower(str_replace(['{', '}'], '', $offerData['uuid']));
            $offerData['href'] = $cleanUuid;
        }

        $offerData['image'] = [
            'src' => $this->getImageLink($offerData),
            'alt' => $offerData['name'],
            'width' => 841,
            'height' => 594
        ];

        $offerData['elementName'] = html_entity_decode($offerData['vendorName']);
        //hotfix special char
        $offerData['elementName'] = str_replace('&#39;', "'", $offerData['elementName']);

        return $offerData;
    }

    public function getElementLink($offerData)
    {
        $isContao5 = C4GVersionProvider::isContaoVersionAtLeast('5.0.0');
        $offerData['elementName'] = html_entity_decode($offerData['vendorName']);

        //hotfix special char
        $offerData['elementName'] = str_replace('&#39;', "'", $offerData['elementName']);

        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $elementPage = PageModel::findByPk($objSettings->showcaseDetailPage);
        if ($elementPage !== null) {
            if ($isContao5) {
                $url = $elementPage->getAbsoluteUrl();
            } else {
                $url = $elementPage->getAbsoluteUrl();
            }

            if ($url) {
                $href = '';
                if (C4GUtils::endsWith($url, '.html')) {
                    $href = str_replace('.html', '/' . strtolower(str_replace(['{', '}'], '', $offerData['vendorAlias'])) . '.html', $url);
                } else if (str_ends_with($url, $offerData['vendorAlias'])) {
                    $href = $url;
                } else if ($offerData['vendorAlias']) {
                    $href = $url . '/' . strtolower(str_replace(['{', '}'], '', $offerData['vendorAlias']));
                }
                return $href ?: '';
            }
        }

        return "";
    }

    public function getImageLink($offerData)
    {
        $this->getSettings();

        return $this->fileUtils->addUrlToPathAndGetImage($this->settings->cdnUrl, $offerData['imageCDN']);
    }

    private function getSettings()
    {
        if (!$this->settings) {
            $this->settings = GutesioOperatorSettingsModel::findSettings();
        }
    }
}