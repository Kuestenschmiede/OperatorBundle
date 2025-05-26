<?php

namespace gutesio\OperatorBundle\Classes\Helper;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\C4GVersionProvider;
use Contao\Database;
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
    }

    public function initUtils()
    {
        if ($this->fileUtils === null) {
            $this->fileUtils = new FileUtils();
        }
    }

    public function setImageAndDetailLinks($offerData)
    {
        $this->initUtils();
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
        $this->initUtils();
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
        $this->initUtils();
        $this->getSettings();

        return $this->fileUtils->addUrlToPathAndGetImage($this->settings->cdnUrl, $offerData['imageCDN']);
    }

    public function loadOfferTagRelations($offerData)
    {
        $this->initUtils();
        $offerUuids = [];

        foreach ($offerData as $offer) {
            $offerUuids[] = $offer['uuid'];
        }

        $sql = "SELECT rel.tagId as tagId, rel.childId as childId, val.tagFieldKey as tagFieldKey, val.tagFieldValue as tagFieldValue FROM tl_gutesio_data_child_tag rel JOIN tl_gutesio_data_child_tag_values val ON val.childId = rel.childId WHERE rel.`childId` " . C4GUtils::buildInString($offerUuids);
        $offerTagRelations = Database::getInstance()->prepare($sql)->execute(...$offerUuids)->fetchAllAssoc();
        $sortedOfferTagRelations = [];

        foreach ($offerTagRelations as $offerTagRelation) {

            if (!key_exists($offerTagRelation['childId'], $sortedOfferTagRelations)
                || !is_array($sortedOfferTagRelations[$offerTagRelation['childId']])
            ) {
                $sortedOfferTagRelations[$offerTagRelation['childId']] = [];
            }
            $sortedOfferTagRelations[$offerTagRelation['childId']][] = $offerTagRelation;
        }

        return $sortedOfferTagRelations;
    }

    public function generateTagLinks($tagData, $offerTagRelations)
    {
        $this->initUtils();
        $tagLinks = [];
        $usedTags = [];

        foreach ($offerTagRelations as $relation) {
            $currentTag = $tagData[$relation['tagId']];

            if (array_key_exists($relation['tagId'], $usedTags)) {
                continue;
            }

            $tagLinks[] = [
                'name' => $currentTag['name'],
                'image' => [
                    'src' => $currentTag['imageCDN'] ? $this->getImageLink($currentTag) : "",
                    'alt' => $currentTag['name'],
                    'width' => 841,
                    'height' => 594
                ],
                'linkHref' => $relation['tagFieldValue'],
                'class' => $relation['tagFieldKey']
            ];
            $usedTags[$relation['tagId']] = true;
        }

        return $tagLinks;
    }

    private function getSettings()
    {
        if (!$this->settings) {
            $this->settings = GutesioOperatorSettingsModel::findSettings();
        }
    }
}