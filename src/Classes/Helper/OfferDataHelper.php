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

        switch ($offerData['type']) {
            case 'product':
                $childPage = PageModel::findByPk($this->settings->productDetailPage);
                break;
            case 'event':
                $childPage = PageModel::findByPk($this->settings->eventDetailPage);
                break;
            case 'job':
                $childPage = PageModel::findByPk($this->settings->jobDetailPage);
                break;
            case 'arrangement':
                $childPage = PageModel::findByPk($this->settings->arrangementDetailPage);
                break;
            case 'service':
                $childPage = PageModel::findByPk($this->settings->serviceDetailPage);
                break;
            case 'person':
                $childPage = PageModel::findByPk($this->settings->personDetailPage);
                break;
            case 'voucher':
                $childPage = PageModel::findByPk($this->settings->voucherDetailPage);
                break;
            default:
                $childPage = PageModel::findByPk($this->settings->eventDetailPage);
        }

        if ($childPage !== null) {
            $cleanUuid = strtolower(str_replace(['{', '}'], '', $offerData['uuid']));
            // use alias instead of uuid in the URL, if possible
            if (array_key_exists('alias', $offerData) && $offerData['alias']) {
                $offerData['href'] = $offerData['alias'];
            } else {
                $offerData['href'] = $cleanUuid;
            }
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
            if ($isContao5 && $offerData['vendorAlias']) {
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

    public function getOrderClause($filterData, $offset, $limit): string
    {
        $sql = "";
        if ($filterData['sort']) {
            if ($filterData['sort'] === "price_asc") {
                $sql = sprintf(" ORDER BY price ASC LIMIT %s, %s", $offset, $limit);
            } else if ($filterData['sort'] === "price_desc") {
                $sql = sprintf(" ORDER BY price DESC LIMIT %s, %s", $offset, $limit);
            } else if ($filterData['sort'] === "name_asc") {
                $sql = sprintf(" ORDER BY name ASC LIMIT %s, %s", $offset, $limit);
            } else if ($filterData['sort'] === "name_desc") {
                $sql = sprintf(" ORDER BY name DESC LIMIT %s, %s", $offset, $limit);
            }
        }

        if ($sql === "") {
            $seed = $this->getSeedForLoading();
            $sql = sprintf(" ORDER BY RAND($seed) LIMIT %s, %s", $offset, $limit);
        }

        return $sql;
    }

    public function handleFilter($filterData, $parameters, $sql)
    {
        if ($filterData['tags']) {
            $sql .= " AND tl_gutesio_data_child_tag.tagId " . C4GUtils::buildInString($filterData['tags']);
            $parameters = array_merge($parameters, $filterData['tags']);
        }
        if ($filterData['categories']) {
            $sql .= " AND typeId " . C4GUtils::buildInString($filterData['categories']);
            $parameters = array_merge($parameters, $filterData['categories']);
        }
        if ($filterData['childs']) {
            $sql .= " AND a.uuid " . C4GUtils::buildInString($filterData['childs']);
            $parameters = array_merge($parameters, $filterData['childs']);
        }
        if ($filterData['location']) {
            $sql .= " AND (tl_gutesio_data_element.locationCity LIKE ? OR tl_gutesio_data_element.locationZip LIKE ?)";
            $parameters[] = $filterData['location'];
            $parameters[] = $filterData['location'];
        }

        return ['params' => $parameters, 'sql' => $sql];
    }

    public function getSeedForLoading()
    {
        $seed = (new \DateTime())->getTimestamp();
        // remove seconds from timestamp
        // this way we achieve a new random sort order each minute,
        // while still being able to randomly sort in SQL over multiple requests
        $seed = $seed - ($seed % 60);

        return $seed;
    }

    private function getSettings()
    {
        if (!$this->settings) {
            $this->settings = GutesioOperatorSettingsModel::findSettings();
        }
    }
}