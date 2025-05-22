<?php

namespace gutesio\OperatorBundle\Classes\Helper;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\C4GVersionProvider;
use Contao\PageModel;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

class OfferDataHelper
{
    private $cdnUrl = "";

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
                $url = $elementPage->getAbsoluteUrl(['parameters' => "/" . $offerData['vendorAlias']]);
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
        if ($this->cdnUrl === "") {
            $objSettings = GutesioOperatorSettingsModel::findSettings();
            $this->cdnUrl = $objSettings->cdnUrl;
        }

        return $this->fileUtils->addUrlToPathAndGetImage($this->cdnUrl, $offerData['imageCDN']);
    }
}