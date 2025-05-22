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
use con4gis\CoreBundle\Classes\C4GVersionProvider;
use con4gis\FrameworkBundle\Classes\Utility\RegularExpression;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use gutesio\DataModelBundle\Classes\ChildFullTextContentUpdater;
use gutesio\DataModelBundle\Classes\ShowcaseResultConverter;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\DataModelBundle\Classes\TagDetailFieldGenerator;
use gutesio\DataModelBundle\Classes\TagFieldUtil;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataElementModel;
use gutesio\OperatorBundle\Classes\Cache\OfferDataCache;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use Symfony\Component\HttpFoundation\Request;

class OfferLoaderService
{
    /**
     * @var ModuleModel
     */
    private $model = null;

    private $pageUrl = '';

    /**
     * @var Request
     */
    private $request;

    private $randomSeed;

    private $limit = 10;

    private $cachedTagData = [];

    private $newCacheTagData = [];

    private $cachedAdditionalData = [];

    private $newCacheAdditionalData = [];

    /**
     * @var VisitCounterService
     */
    private $visitCounter = null;

    /**
     * @var EventDataService
     */
    private $eventDataService = null;

    /**
     * OfferLoaderService constructor.
     */
    public function __construct(
        VisitCounterService $visitCounter,
        EventDataService $eventDataService
    ) {
        $this->visitCounter = $visitCounter;
        $this->eventDataService = $eventDataService;
    }

    private function setup()
    {
        $this->createRandomSeed($this->request);
    }

    public function getListData($search, $offset, $type, $filterData, $determineOrientation = false)
    {
        $this->setup();
        $limit = $this->limit;
        $tagIds = $filterData && key_exists('tagIds', $filterData) ? $filterData['tagIds'] : [];
        if (is_array($tagIds) && (count($tagIds) > 0) && strpos($tagIds[0],',')) {
            $tagIds = explode(',',$tagIds[0]);
        }
        $categoryIds = key_exists('categoryIds', $filterData) ? $filterData['categoryIds'] : [];

        $tagFilter = $tagIds && (count($tagIds) > 0);
        $categoryFilter = $categoryIds && (count($categoryIds) > 0);
        $dateFilter = (key_exists('filterFrom', $filterData) ? $filterData['filterFrom'] : false) || (key_exists('filterUntil', $filterData) ? $filterData['filterUntil'] : false);
        $sortFilter = key_exists('sorting', $filterData) ? $filterData['sorting'] : false;

        $hasFilter = $tagFilter || $categoryFilter || $sortFilter || $dateFilter;

        $terms = explode(' ', $search);
        $database = Database::getInstance();
        foreach ($terms as $key => $term) {
            $terms[$key] = "$term*";
        }
        $termString = implode(',', $terms);

        $this->loadFromCache();
        $offerData = [];

        $types = StringUtil::deserialize($this->model->gutesio_child_type, true);

        if (in_array("event", $types)) {
            $eventFilterData = [
                'tags' => $tagFilter ? $tagIds : [],
                'categories' => $categoryFilter ? $categoryIds : [],
                'date' => $dateFilter ? ['from' => $filterData['filterFrom'], 'until' => $filterData['filterUntil']] : [],
                'sort' => $sortFilter ? $filterData['sorting'] : 'date'
            ];

            $eventResults = $this->eventDataService->getEventData($termString, $offset, $eventFilterData, $limit, $determineOrientation);
            $offerData = array_merge($offerData, $eventResults);
            $tmpOffset = $offset;
        }

        $eventsOnly = (count($types) === 1 && $types[0] === "event");
        // avoid calling legacy logic if it's event data only
        if (!$eventsOnly) {
            // TODO dieser Teil wird langfristig durch die einzelnen Service-Aufrufe ersetzt
            // begin legacy data loading
            if ($hasFilter) {
                // raise limit and ignore offset temporarily
                $limit = 5000;
                $tmpOffset = $offset;
                $offset = 0;
            }
            //ToDo compare UPPER terms
            if ($search !== '') {
//                $terms = explode(' ', $search);
                $results = $this->getFullTextData($terms, $offset, $type, $limit, $dateFilter, $determineOrientation);
            } else {
                //ToDo performance check
                $results = $this->getFullTextDataWithoutTerms($offset, $type, $limit, $dateFilter, $determineOrientation);
            }

            $this->writeOfferDataToCache();

            if ($tagFilter) {
                // filter using actual limit & offset
                $results = $this->applyTagFilter($results, $tagIds, $tmpOffset, $this->limit);
            }

            if ($categoryFilter) {
                // filter using actual limit & offset
                $results = $this->applyCategoryFilter($results, $categoryIds, $tmpOffset, $this->limit);
            }

            if ($dateFilter) {
                $results = $this->applyRangeFilter($results, $filterData['filterFrom'] ?: 0, $filterData['filterUntil'] ?: 0);
            }
            // end legacy data loading
            $offerData = array_merge($offerData, $results);
        }

        $offerData = $this->sortOfferData($sortFilter, $filterData, $offerData);
        if ($hasFilter && !$eventsOnly) {
            $offerData = array_slice($offerData, $tmpOffset, $this->limit);
        }

        // data cleaning
        foreach ($offerData as $key => $result) {
            $offerData[$key]['shortDescription'] = html_entity_decode($result['shortDescription']);
            $offerData[$key]['name'] = html_entity_decode($result['name']);

            if ($result['foreignLink']) {
                // search for http to avoid prepending https to insecure links
                if (!str_contains($result['foreignLink'], 'http')) {
                    $offerData[$key]['foreignLink'] = C4GUtils::addProtocolToLink($result['foreignLink']);
                }
            }
        }

        return $offerData;
    }

    private function sortOfferData(string $sortFilter, array $filterData, array $offers)
    {
        if ($sortFilter) {
            switch ($sortFilter) {
                case 'date':
                    $dateOffers = [];
                    $noDateOffers = [];
                    foreach ($offers as $offer) {
                        if ($offer['beginDate']) {
                            $dateOffers[] = $offer;
                        } else {
                            $noDateOffers[] = $offer;
                        }
                    }
                    $sort = 'desc';
                    usort($dateOffers, function ($a, $b) use ($sort) {
                        $a['storeBeginTime'] = $a['storeBeginTime'] ?: strtotime($a['beginTime']);
                        $b['storeBeginTime'] = $b['storeBeginTime'] ?: strtotime($b['beginTime']);
                        $a['storeBeginDate'] = $a['storeBeginDate'] ?: strtotime($a['beginDate']);
                        $b['storeBeginDate'] = $b['storeBeginDate'] ?: strtotime($b['beginDate']);

                        $aDate = $a['storeBeginTime'] ? $a['storeBeginDate'] + $a['storeBeginTime'] : $a['storeBeginDate'];
                        $bDate = $b['storeBeginTime'] ? $b['storeBeginDate'] + $b['storeBeginTime'] : $b['storeBeginDate'];
                        if ($aDate === null) {
                            return 1;
                        }
                        if ($bDate === null) {
                            return -1;
                        }
                        if ($aDate > $bDate) {
                            return 1;
                        } elseif ($aDate < $bDate) {
                            return -1;
                        }

                        return 0;
                    });
                    foreach ($noDateOffers as $noDateOffer) {
                        $index = rand(0, count($dateOffers));
                        array_splice($dateOffers, $index, 0, [$noDateOffer]);
                    }
                    $offers = $dateOffers;
                    break;
                case 'price_asc':
                    $sort = 'asc';
                    usort($offers, function ($a, $b) use ($sort) {
                        $aPrice = $a['rawPrice'];
                        $bPrice = $b['rawPrice'];
                        if ($aPrice === null) {
                            return 1;
                        }
                        if ($bPrice === null) {
                            return -1;
                        }
                        if ($aPrice > $bPrice) {
                            return 1;
                        } elseif ($aPrice < $bPrice) {
                            return -1;
                        }

                        return 0;
                    });

                    break;
                case 'price_desc':
                    $sort = 'desc';
                    usort($offers, function ($a, $b) use ($sort) {
                        $aPrice = $a['rawPrice'];
                        $bPrice = $b['rawPrice'];
                        if ($aPrice === null) {
                            return 1;
                        }
                        if ($bPrice === null) {
                            return -1;
                        }
                        if ($aPrice > $bPrice) {
                            return -1;
                        } elseif ($aPrice < $bPrice) {
                            return 1;
                        }

                        return 0;
                    });
                    break;
                case 'name_asc':
                    $sort = 'asc';
                    usort($offers, function ($a, $b) use ($sort) {
                        $aCaption = $a['name'];
                        $bCaption = $b['name'];
                        if ($aCaption === null) {
                            return 1;
                        }
                        if ($bCaption === null) {
                            return -1;
                        }
                        if ($aCaption > $bCaption) {
                            return 1;
                        } elseif ($aCaption < $bCaption) {
                            return -1;
                        }

                        return 0;
                    });

                    break;
                case 'name_desc':
                    $sort = 'desc';
                    usort($offers, function ($a, $b) use ($sort) {
                        $aCaption = $a['name'];
                        $bCaption = $b['name'];
                        if ($aCaption === null) {
                            return 1;
                        }
                        if ($bCaption === null) {
                            return -1;
                        }
                        if ($aCaption > $bCaption) {
                            return -1;
                        } elseif ($aCaption < $bCaption) {
                            return 1;
                        }

                        return 0;
                    });
                    break;
                case 'tstmp_desc':
                    $sort = 'desc';
                    usort($offers, function ($a, $b) use ($sort) {
                        $aTstmp = $a['tstamp'];
                        $bTstmp = $b['tstamp'];
                        if ($aTstmp === null) {
                            return 1;
                        }
                        if ($bTstmp === null) {
                            return -1;
                        }
                        if ($aTstmp > $bTstmp) {
                            return -1;
                        } elseif ($aTstmp < $bTstmp) {
                            return 1;
                        }

                        return 0;
                    });
                    break;
                default: //random
                    break;
            }
        }

        return $offers;
    }

    public function getFullTextData(
        array $terms,
              $offset = 0,
        string $type = '',
        int $limit = 0,
        bool $dateFilter = false,
        bool $determineOrientation = false
    ) {
        System::loadLanguageFile('gutesio_frontend');
        $rawTermString = implode(' ', $terms);
        $database = Database::getInstance();
        foreach ($terms as $key => $term) {
            $terms[$key] = "$term*";
        }
        $termString = implode(',', $terms);
        $updater = new ChildFullTextContentUpdater();
        if ($updater->isFullText() !== true) {
            $updater->addFullText();
        }

        $childDataMode = $this->model->gutesio_child_data_mode;

        $types = [];
        $categories = [];
        if ($childDataMode == '1') {
            $types = StringUtil::deserialize($this->model->gutesio_child_type, true);
        } elseif ($childDataMode == '2') {
            $categories = StringUtil::deserialize($this->model->gutesio_child_category, true);
        } elseif ($childDataMode == '3') {
            $tags = StringUtil::deserialize($this->model->gutesio_child_tag, true);
        }

        // remove events
        $tmpTypes = [];
        foreach ($types as $type) {
            if ($type !== "event") {
                $tmpTypes[] = $type;
            }
        }
        $types = $tmpTypes;

        $arrTagFieldClause = $this->createTagFieldClause();
        $strTagFieldClause = $arrTagFieldClause['str'];
        $fieldCount = $arrTagFieldClause['count'];
        $sqlExtendedCategoryTerms = ' OR tl_gutesio_data_child_type.extendedSearchTerms LIKE ?';

        if (empty($types) && empty($categories)) {
            if (!empty($tags)) {
                $parameters = $tags;
                for ($i = 0; $i <= $fieldCount; $i++) {
                    $parameters[] = '%' . $rawTermString . '%';
                }
                $parameters[] = (int) $offset;
                $parameters[] = $limit;
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) as relevance, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag ON tl_gutesio_data_child_tag.childId = a.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                WHERE a.published = 1 AND (match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) OR ' . $strTagFieldClause . $sqlExtendedCategoryTerms . ') ' . '
                AND tl_gutesio_data_child_tag.tagId ' . C4GUtils::buildInString($tags) .
                    ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                    ' ORDER BY relevance DESC LIMIT ?, ?'
                )->execute(...$parameters)->fetchAllAssoc();
            } else {
                $parameters = [];
                for ($i = 0; $i <= $fieldCount; $i++) {
                    $parameters[] = '%' . $rawTermString . '%';
                }
//                $parameters[] = (int) $offset;
//                $parameters[] = $limit;
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) as relevance, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                WHERE a.published = 1 AND (match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) OR ' . $strTagFieldClause . $sqlExtendedCategoryTerms . ') ' . '
                AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())
                ORDER BY relevance DESC LIMIT 0, 5000 ') //Todo ?, ? Hotfix offset error
                ->execute(...$parameters)->fetchAllAssoc();
            }
        } elseif (empty($categories)) {
            $parameters = $types;
            for ($i = 0; $i <= $fieldCount; $i++) {
                $parameters[] = '%' . $rawTermString . '%';
            }
//            $parameters[] = (int) $offset;  //Hotfix offset error
//            $parameters[] = $limit;
            $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) as relevance, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                WHERE a.published = 1 AND type ' . C4GUtils::buildInString($types) .
                'AND (match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) OR ' . $strTagFieldClause . $sqlExtendedCategoryTerms . ') ' .
                ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                ' ORDER BY relevance DESC LIMIT 0, 5000' //Todo ?, ? Hotfix offset error
            )->execute(...$parameters)->fetchAllAssoc();
        } else {
            $parameters = $categories;
            for ($i = 0; $i < $fieldCount; $i++) {
                $parameters[] = '%' . $rawTermString . '%';
            }
            $parameters[] = (int) $offset;
            $parameters[] = $limit;
            $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) as relevance, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                WHERE a.published = 1 AND (match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) OR ' . $strTagFieldClause . ') ' . '
                AND a.parentChildId ' . C4GUtils::buildInString($categories) .
                ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                ' ORDER BY relevance DESC LIMIT ?, ?'
            )->execute(...$parameters)->fetchAllAssoc();
        }
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;
        $fileUtils = new FileUtils();

        foreach ($childRows as $key => $row) {
            if ($row['imageCDN']) {
                if ($determineOrientation) {
                    $imageCDN = $fileUtils->addUrlToPath($cdnUrl,$row['imageCDN']);
                    $result = $fileUtils->getImageSizeAndOrientation($imageCDN);

                    if ($result && $result[1] !== 'portrait') {
                      $width = 841;
                      $height = 594;
                    } else {
                        $width = 594;
                        $height = 841;
                    }
                    $imageCDN = $fileUtils->addUrlToPath($cdnUrl,$row['imageCDN'], $width, $height);
                } else {
                    $width = 841;
                    $height = 594;
                    $imageCDN = $fileUtils->addUrlToPath($cdnUrl,$row['imageCDN'], $width);
                }

                $childRows[$key]['image'] = [
                    'src' => $imageCDN,
                    'alt' => $row['name'],
                    'width' => $width,
                    'height' => $height,
                ];
            } else {
                unset($childRows[$key]['image']);
            }

            $childRows[$key]['href'] = strtolower(str_replace(['{', '}'], ['', ''], $row['uuid']));

            $childRows[$key] = $this->getTagData($row['uuid'], $childRows[$key], $key);
        }

        return $this->getAdditionalData($childRows, $dateFilter);
    }

    public function getFullTextDataWithoutTerms(
        $offset = 0,
        string $type = '',
        int $limit = 0,
        bool $dateFilter = false,
        bool $determineOrientation = false
    ) {
        System::loadLanguageFile('gutesio_frontend');
        $database = Database::getInstance();

        $childDataMode = $this->model->gutesio_child_data_mode;

        $types = [];
        $categories = [];
        if ($childDataMode == '1') {
            $types = StringUtil::deserialize($this->model->gutesio_child_type, true);
        } elseif ($childDataMode == '2') {
            $categories = StringUtil::deserialize($this->model->gutesio_child_category, true);
        } elseif ($childDataMode == '3') {
            $tags = StringUtil::deserialize($this->model->gutesio_child_tag, true);
        }


        // remove events
        $tmpTypes = [];
        foreach ($types as $type) {
            if ($type !== "event") {
                $tmpTypes[] = $type;
            }
        }
        $types = $tmpTypes;

        $fileUtils = new FileUtils();

        $elements = $this->model->gutesio_data_elements ? StringUtil::deserialize($this->model->gutesio_data_elements, true) : [];

        if (empty($types) && empty($categories)) {
            if (!empty($tags)) {
                if (count($elements)) {
                    $parameters = $tags;
                    $parameters = array_merge($parameters, $elements);
                    $parameters[] = (int) $offset;
                    $parameters[] = $limit;
                    $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                        'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                        (CASE ' . '
                            WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                            WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                            WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                            WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                        ELSE NULL END) AS shortDescription, ' . '
                        tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                        tl_gutesio_data_element.uuid as elementId, ' . '
                        a.uuid as alias, ' . '
                        tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                        tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                        FROM tl_gutesio_data_child a ' . '
                        LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                        LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                        JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                        LEFT JOIN tl_gutesio_data_child_tag ON tl_gutesio_data_child_tag.childId = a.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                        WHERE a.published = 1 AND tl_gutesio_data_child_tag.tagId ' . C4GUtils::buildInString($tags) .
                                ' AND elementId ' . C4GUtils::buildInString($elements) .
                                ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                                ' ORDER BY RAND(' . $this->randomSeed . ') LIMIT '.$offset.', '.$limit
                    )->execute($parameters)->fetchAllAssoc();
                } else {
                    $parameters = $tags;
                    $parameters[] = (int) $offset;
                    $parameters[] = $limit;
                    $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                        'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag ON tl_gutesio_data_child_tag.childId = a.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                WHERE a.published = 1 AND tl_gutesio_data_child_tag.tagId ' . C4GUtils::buildInString($tags) .
                        ' AND elementId ' . C4GUtils::buildInString($elements) .
                        ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                        ' ORDER BY RAND(' . $this->randomSeed . ') LIMIT '.$offset.', '.$limit
                    )->execute($parameters)->fetchAllAssoc();
                }
            } else {
                if (count($elements)) {
                    $parameters = [];
                    $parameters = array_merge($parameters, $elements);
//                    $parameters[] = (int)$offset;
//                    $parameters[] = $limit;
                    $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                        'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                WHERE elementId ' . C4GUtils::buildInString($elements) .' AND a.published = 1 AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())
                ORDER BY RAND(' . $this->randomSeed . ') LIMIT ' . $offset . ', ' . $limit)
                        ->execute(
                            ...$parameters
                        )->fetchAllAssoc();
                } else {
//                    $parameters = [];
//                    $parameters[] = (int)$offset;
                    $parameters[] = $limit;
                    $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                        'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                WHERE a.published = 1 AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())
                ORDER BY RAND(' . $this->randomSeed . ') LIMIT ' . $offset . ', ' . $limit)
                        ->execute()->fetchAllAssoc();
                }
            }
        } elseif (empty($categories)) {
            if (count($elements)) {
                $parameters = array_merge($types, $elements);
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                WHERE a.published = 1 AND tl_gutesio_data_child_type.type ' . C4GUtils::buildInString($types) .
                    ' AND elementId ' . C4GUtils::buildInString($elements) .
                    ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                    ' ORDER BY RAND(' . $this->randomSeed . ') LIMIT '.$offset.', '.$limit
                )->execute(...$parameters)->fetchAllAssoc();
            } else {
                $parameters = $types;
                $parameters[] = (int) $offset;
                $parameters[] = $limit;
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                WHERE a.published = 1 AND tl_gutesio_data_child_type.type ' . C4GUtils::buildInString($types) .
                    ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                    ' ORDER BY RAND(' . $this->randomSeed . ') LIMIT '.$offset.', '.$limit
                )->execute($parameters)->fetchAllAssoc();
            }
        } else {
            if (count($elements)) {
                $parameters = $categories;
                $parameters = array_merge($parameters, $elements);
                $parameters[] = (int)$offset;
                $parameters[] = $limit;
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                WHERE a.published = 1 AND a.typeId ' . C4GUtils::buildInString($categories) .
                    ' AND elementId ' . C4GUtils::buildInString($elements) .
                    ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                    ' ORDER BY RAND(' . $this->randomSeed . ') LIMIT ' . $offset . ', ' . $limit
                )->execute($parameters)->fetchAllAssoc();
            } else {
                $parameters = $categories;
                $parameters[] = (int) $offset;
                $parameters[] = $limit;
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                WHERE a.published = 1 AND a.typeId ' . C4GUtils::buildInString($categories) .
                    ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                    ' ORDER BY RAND(' . $this->randomSeed . ') LIMIT '.$offset.', '.$limit
                )->execute($parameters)->fetchAllAssoc();
            }
        }
        $objSettings = GutesioOperatorSettingsModel::findSettings();

        $cdnUrl = $objSettings->cdnUrl;
        foreach ($childRows as $key => $row) {
            $image = $row['imageCDN'];// && FilesModel::findByUuid($row['imageOffer']) ? FilesModel::findByUuid($row['imageOffer']) : FilesModel::findByUuid($row['image']);
            if ($image) {
                if ($determineOrientation) {
                    $imageCDN = $fileUtils->addUrlToPath($cdnUrl,$row['imageCDN']);
                    $result = [0,"0"];//$fileUtils->getImageSizeAndOrientation($imageCDN);

                    if ($result && $result[1] !== 'portrait') {
                        $width = 841;
                        $height = 594;
                    } else {
                        $width = 594;
                        $height = 841;
                    }
                    $imageCDN = $fileUtils->addUrlToPath($cdnUrl,$row['imageCDN'], $width, $height);
                } else {
                    $width = 841;
                    $height = 594;
                    $imageCDN = $fileUtils->addUrlToPath($cdnUrl,$row['imageCDN'], $width);
                }

                $childRows[$key]['image'] = [
                    'src' => $imageCDN,
                    'alt' => /*$imageModel->meta && unserialize($imageModel->meta)['de'] ? unserialize($imageModel->meta)['de']['alt'] : */$row['name'],
                    'height' => $height,
                    'width' => $width,
                ];
            }/* else {
                unset($childRows[$key]['image']);
            }*/
//            unset($childRows[$key]['image']);

            $childRows[$key]['href'] = strtolower(str_replace(['{', '}'], ['', ''], $row['uuid']));

            $childRows[$key] = $this->getTagData($row['uuid'], $childRows[$key], $key);
        }

        $data = $this->getAdditionalData($childRows, $dateFilter);

        return $data;
    }

    public function getDetailData($alias, $typeKeys)
    {
        $dataset = $this->getSingleDataset($alias, true, false, $typeKeys);
        if ($dataset) {
            $this->visitCounter->countOfferVisit($dataset['uuid'], $dataset['memberId']);
        }

        return $dataset;
    }

    public function getPreviewData($alias)
    {
        $previewData = $this->getSingleDataset($alias, false, true);

        return $previewData;
    }

    public function getSingleDataset($alias, $published, $isPreview = false, $typeKeys = [])
    {
        $database = Database::getInstance();
        $alias = $this->cleanAlias($alias);

        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;
        $fileUtils = new FileUtils();

        if (is_array($typeKeys) && count($typeKeys) > 0) {
            $keyString = '(';
            foreach ($typeKeys as $key => $id) {
                $keyString .= "\"$id\"";
                if (array_key_last($typeKeys) !== $key) {
                    $keyString .= ',';
                }
            }
            $keyString .= ')';

            $sql = 'SELECT DISTINCT a.id, a.parentChildId, a.uuid, a.tstamp, a.typeId, ' . '
            a.name, a.imageCDN, a.imageGalleryCDN, a.imageCredits, a.source, a.videoType, a.videoLink, a.videoPreviewImageCDN, a.memberId, a.infoFileCDN, a.offerForSale,' . '
            (CASE ' . '
                WHEN a.description IS NOT NULL THEN a.description ' . '
                WHEN b.description IS NOT NULL THEN b.description ' . '
                WHEN c.description IS NOT NULL THEN c.description ' . '
                WHEN d.description IS NOT NULL THEN d.description ' . '
            ELSE NULL END) AS description, ' . '
            tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, 
            tl_gutesio_data_element.uuid as elementId, 
            tl_gutesio_data_child_type.extendedSearchTerms as extendedSearchTerms FROM tl_gutesio_data_child a ' . '
            LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
            LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
            LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
            LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
            LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
            JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
            WHERE a.uuid = ? AND tl_gutesio_data_child_type.type IN '.$keyString;
        } else {
            $sql = 'SELECT DISTINCT a.id, a.parentChildId, a.uuid, a.tstamp, a.typeId, ' . '
            a.name, a.imageCDN, a.imageGalleryCDN, a.imageCredits, a.source, a.videoType, a.videoLink, a.videoPreviewImageCDN, a.memberId, a.infoFileCDN, a.offerForSale,' . '
            (CASE ' . '
                WHEN a.description IS NOT NULL THEN a.description ' . '
                WHEN b.description IS NOT NULL THEN b.description ' . '
                WHEN c.description IS NOT NULL THEN c.description ' . '
                WHEN d.description IS NOT NULL THEN d.description ' . '
            ELSE NULL END) AS description, ' . '
            tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, 
            tl_gutesio_data_element.uuid as elementId, 
            tl_gutesio_data_child_type.extendedSearchTerms as extendedSearchTerms FROM tl_gutesio_data_child a ' . '
            LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
            LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
            LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
            LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
            LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
            JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
            WHERE a.uuid = ?';
        }

        if ($published) {
            $sql .= ' AND a.published = 1';
        }
        $rows = $database->prepare($sql)->execute('{' . $alias . '}')->fetchAllAssoc();

        if (empty($rows)) {
            return [];
        }

        foreach ($rows as $key => $row) {
            $result = $database->prepare(
                'SELECT * FROM tl_gutesio_data_child_connection WHERE childId = ? LIMIT 1'
            )->execute('{' . strtoupper($alias) . '}')->fetchAssoc();
            $result = $database->prepare(
                'SELECT * FROM tl_gutesio_data_element WHERE uuid = ?'
            )->execute($result['elementId'])->fetchAssoc();
            $rows[$key]['email'] = $result['email'];
            $rows[$key]['phone'] = html_entity_decode($result['phone']);
            $rows[$key]['website'] = $result['website'];
            $rows[$key]['websiteLabel'] = key_exists('websiteLabel', $result) ? $result['websiteLabel'] : '';
            if ($result['opening_hours'] && strpos($result['opening_hours'], '"') === 0) {
                $rows[$key]['opening_hours'] = html_entity_decode(str_replace(array("\r\n", "\r", "\n"), '', $result['opening_hours']));
            } else {
                $rows[$key]['opening_hours'] = html_entity_decode($result['opening_hours']);
            }
            $rows[$key]['deviatingPhoneHours'] = $result['deviatingPhoneHours'];
            $rows[$key]['phoneHours'] = html_entity_decode($result['phoneHours']);
            $rows[$key]['opening_hours_additional'] = html_entity_decode($result['opening_hours_additional']);
            $rows[$key]['contactName'] = html_entity_decode($result['contactName']);
            $rows[$key]['contactAdditionalName'] = html_entity_decode($result['contactAdditionalName']);
            $rows[$key]['contactStreet'] = $result['contactStreet'];
            $rows[$key]['contactStreetNumber'] = $result['contactStreetNumber'];
            $rows[$key]['contactZip'] = $result['contactZip'];
            $rows[$key]['contactCity'] = $result['contactCity'];
            $rows[$key]['locationName'] = key_exists('name', $result) ? html_entity_decode($result['name']) : '';
            $rows[$key]['locationAdditionalName'] = key_exists('locationAdditionalName', $result) ? $result['locationAdditionalName'] : '';
            $rows[$key]['locationStreet'] = $result['locationStreet'];
            $rows[$key]['locationStreetNumber'] = $result['locationStreetNumber'];
            $rows[$key]['locationZip'] = $result['locationZip'];
            $rows[$key]['locationCity'] = $result['locationCity'];
            $rows[$key]['geox'] = $result['geox'];
            $rows[$key]['geoy'] = $result['geoy'];
            $rows[$key]['elementId'] = $result['uuid'];

            $rows[$key]['videoPreview'] = [
                'videoType' => $rows[$key]['videoType'],
                'video' => html_entity_decode($rows[$key]['videoLink']),
            ];
            if ($rows[$key]['videoPreviewImageCDN']) {
                //$model = FilesModel::findByUuid(StringUtil::deserialize($rows[$key]['videoPreviewImage']));
                $file = $rows[$key]['videoPreviewImageCDN'];
                if ($file) {
                    $converter = new ShowcaseResultConverter();
                    $rows[$key]['videoPreview']['videoPreviewImage'] = $converter->createFileDataFromFile($file);
                    $rows[$key]['videoPreviewImage'] = $rows[$key]['videoPreview']['videoPreviewImage'];
                }
            }

            if ($row['infoFileCDN']) {
                $rows[$key]['infoFile'] = [
                    'name' => 'Info',
                    'path' => $fileUtils->addUrlToPath($cdnUrl,$row['infoFileCDN']),
                    'changed' => false,
                    'data' => [],
                ];
            }

            $images = [];
            if ($row['imageGalleryCDN']) {
                $images = StringUtil::deserialize($row['imageGalleryCDN']);
            }
            if ($rows[$key]['imageCDN']) {
                array_unshift($images, $rows[$key]['imageCDN']);
            }
            $idx = 0;
            foreach ($images as $image) {
                $file = $image;
                if ($file) {
                    $imageCDN = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$file);
                    $result = $fileUtils->getImageSizeAndOrientation($imageCDN);

                    if ($result && $result[1] !== 'portrait') {
                        $width = 841;
                        $height = 594;
                    } else {
                        $width = 594;
                        $height = 841;
                    }

                    $url = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$file,$width,$height);
                    $rows[$key]['imageGallery_' . $idx] = [
                        'src' => $url,
                        'path' => $url,
                        'uuid' => '',
                        'alt' => $row['name'],
                        'name' => $row['name'],
                        'width' => $width,
                        'height' => $height,
                    ];
                    $idx++;
                }
            }

            unset($rows[$key]['image']);

            $tagStmt = $database->prepare(
                'SELECT tl_gutesio_data_tag.* FROM tl_gutesio_data_tag JOIN tl_gutesio_data_child_tag ON ' .
                'tl_gutesio_data_child_tag.tagId = tl_gutesio_data_tag.uuid WHERE tl_gutesio_data_tag.published = 1' .
                ' AND tl_gutesio_data_child_tag.childId = ? AND (tl_gutesio_data_tag.validFrom = 0' .
                ' OR tl_gutesio_data_tag.validFrom IS NULL' .
                ' OR tl_gutesio_data_tag.validFrom <= UNIX_TIMESTAMP() AND (tl_gutesio_data_tag.validUntil = 0' .
                ' OR tl_gutesio_data_tag.validUntil IS NULL' .
                ' OR tl_gutesio_data_tag.validUntil >= UNIX_TIMESTAMP()))'
            );
            $rows[$key]['tags'] = $tagStmt->execute($rows[$key]['uuid'])->fetchAllAssoc();

            //remove duplicated content
            $tagLinks = [];
            foreach ($rows[$key]['tags'] as $tagKey => $tagRow) {
                $tagLinks[$tagRow['name']] = $tagRow;
            }

            $rows[$key]['tags'] = $tagLinks;

            foreach ($rows[$key]['tags'] as $tagKey => $tagRow) {
                $imageFile = $tagRow['imageCDN'];

                if ($imageFile) {
                    $url = $fileUtils->addUrlToPath($cdnUrl,$imageFile);
                    $rows[$key]['tags'][$tagKey]['image'] = [
                        'alt' => $tagRow['name'],
//                        'importantPart' => [
//                            'x' => $imageModel->importantPartX,
//                            'y' => $imageModel->importantPartY,
//                            'width' => $imageModel->importantPartWidth,
//                            'height' => $imageModel->importantPartHeight,
//                        ],
                        'name' => $tagRow['name'],
                        'path' => $url,
                        'src' => $url,
                    ];
                } else {
                    unset($rows[$key]['tags'][$tagKey]['image']);
                }

                if ((string) $tagRow['technicalKey'] !== '') {
                    $fields = TagDetailFieldGenerator::getFieldsForTag($tagRow['technicalKey']);
                    foreach ($fields as $field) {
                        $rows[$key]['tags'][$tagKey]['fields'][] = $field->getConfiguration();
                    }

                    $tagValues = $database->prepare(
                        'SELECT tagFieldKey, tagFieldValue FROM tl_gutesio_data_child_tag_values WHERE childId = ?'
                    )->execute($rows[$key]['uuid'])->fetchAllAssoc();
                    foreach ($tagValues as $tagValue) {
                        // check if $tagValue is relevant for current tag
                        $found = false;
                        foreach ($fields as $field) {
                            if ($field->getName() === $tagValue['tagFieldKey']) {
                                $found = true;

                                break;
                            }
                        }
                        if ($found) {
                            //hotfix
                            $isLink = strpos(strtoupper($tagValue['tagFieldKey']), 'LINK');
                            $isLink = $isLink || preg_match(RegularExpression::EMAIL, $tagValue['tagFieldValue']);
                            if ($isLink) {
                                if (preg_match('/' . RegularExpression::EMAIL . '/', $tagValue['tagFieldValue'])) {
                                    if (strpos($tagValue['tagFieldValue'], 'mailto:') !== 0) {
                                        $tagValue['tagFieldValue'] = 'mailto:' . $tagValue['tagFieldValue'];
                                    }
                                } else {
                                    $tagValue['tagFieldValue'] = C4GUtils::addProtocolToLink($tagValue['tagFieldValue']);
                                }
                                $rows[$key]['tags'][$tagKey]['linkHref'] = $tagValue['tagFieldValue'];
                            }

                            $rows[$key][$tagValue['tagFieldKey']] = $tagValue['tagFieldValue'];
                        }
                    }
                }
            }

            $rows[$key]['internal_type'] = $row['type'];
            // translate type for detail display
            $rows[$key]['displayType'] = $row['typeName'];
            $clientUuid = $this->checkCookieForClientUuid($this->request);
            $wishlist = $database->prepare(
                'SELECT * FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ? AND `dataUuid` = ? ' .
                "AND `dataTable` = 'tl_gutesio_data_child' LIMIT 1"
            )->execute($clientUuid, $row['uuid'])->fetchAssoc();
            if (!empty($wishlist)) {
                $rows[$key]['on_wishlist'] = 1;
            } else {
                $rows[$key]['on_wishlist'] = 0;
            }

            $typeValues = $database->prepare('SELECT * FROM tl_gutesio_data_type_child_values WHERE `childId` = ?')
                ->execute($row['uuid'])->fetchAllAssoc();
            foreach ($typeValues as $typeValue) {
                $rows[$key][$typeValue['typeFieldKey']] = $typeValue['typeFieldValue'];
            }

//            $rows[$key] = $this->getTagData($row['uuid'], $rows[$key], $key);
        }

        $rows = $this->getAdditionalData($rows, false, !$isPreview);

        return $rows[0];
    }

    private function getTagData($uuid, $childRow, $key)
    {
        $database = Database::getInstance();

        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;

        if (key_exists($uuid, $this->cachedTagData)) {
            $childRow['tagLinks'] = $this->cachedTagData[$uuid];
            return $childRow;
        } else {

            $result = $database->prepare('SELECT name, imageCDN, technicalKey, tagFieldValue FROM tl_gutesio_data_tag ' .
                'JOIN tl_gutesio_data_child_tag ON tl_gutesio_data_tag.uuid = tl_gutesio_data_child_tag.tagId ' .
                'JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = tl_gutesio_data_child_tag.childId ' .
                'WHERE tl_gutesio_data_tag.published = 1 AND (tl_gutesio_data_tag.validFrom = 0' .
                ' OR tl_gutesio_data_tag.validFrom IS NULL' .
                ' OR tl_gutesio_data_tag.validFrom <= UNIX_TIMESTAMP() AND (tl_gutesio_data_tag.validUntil = 0' .
                ' OR tl_gutesio_data_tag.validUntil IS NULL' .
                ' OR tl_gutesio_data_tag.validUntil >= UNIX_TIMESTAMP())) AND tl_gutesio_data_child_tag.childId = ?')
                ->execute($uuid)->fetchAllAssoc();

            foreach ($result as $r) {
                //$model = FilesModel::findByUuid($r['image']);
                $file = $r['imageCDN'] ? $cdnUrl.$r['imageCDN'] : false;
                if (key_exists('tagLinks', $childRow)) {
                    foreach ($childRow['tagLinks'] as $addedIcons) {
                        if (($addedIcons['name'] == $r['name']) || ($addedIcons['image']['src'] == $file)) {
                            continue(2);
                        }
                    }
                }

                if ($file) {
                    $icon = [
                        'name' => $r['name'],
                        'image' => [
                            'src' => $file,
                            'alt' => $r['name'],
                            'width' => 100,
                            'height' => 100,
                        ],
                    ];

                    switch ($r['technicalKey']) {
                        case 'tag_delivery':
                        case 'tag_clicknmeet':
                        case 'tag_table_reservation':
                        case 'tag_onlineshop':
                        case 'tag_donation':
                            $tagLink = $r['tagFieldValue'];
                            if ($tagLink) {
                                $icon['linkHref'] = C4GUtils::addProtocolToLink($tagLink);
                            }
                            break;
                        case 'tag_online_reservation':
                            $tagLink = $r['tagFieldValue'];

                            if ($tagLink) {
                                if (preg_match('/' . RegularExpression::EMAIL . '/', $tagLink)) {
                                    if (!str_starts_with($tagLink, 'mailto:')) {
                                        $tagLink = 'mailto:' . $tagLink;
                                    }
                                } else {
                                    $tagLink = C4GUtils::addProtocolToLink($tagLink);
                                }

                                $icon['linkHref'] = $tagLink;
                            }

                            break;
                        default:
                            break;
                    }

                    if ($icon) {
                        if (key_exists('class', $icon)) {
                            $icon['class'] .= $r['technicalKey'];
                        } else {
                            $icon['class'] = $r['technicalKey'];
                        }

                        if (!key_exists('tagLinks',$childRow)) {
                            $childRow['tagLinks'] = [];
                        }

                        $childRow['tagLinks'][] = $icon;

                    }
                }
            }

            if ($childRow['tagLinks']) {
                $this->addTagDataToCache($uuid, $childRow['tagLinks']);
            } else {
                $this->addTagDataToCache($uuid, []);
            }
        }

        return $childRow;
    }

    private function createTagFieldClause()
    {
        $fieldNames = TagFieldUtil::getTagFieldnames();
        $response = [];
        $response['count'] = count($fieldNames);
        $strQuery = '';
        foreach ($fieldNames as $key => $fieldName) {
            $strQuery .= "`tagFieldKey` = '$fieldName' AND `tagFieldValue` LIKE ?";
            if (array_key_last($fieldNames) !== $key) {
                $strQuery .= ' OR ';
            }
        }
        $response['str'] = $strQuery;

        return $response;
    }

    public function getAdditionalData($childRows, $dateFilter = false, $checkEventTime = true)
    {
        $database = Database::getInstance();
        System::loadLanguageFile('tl_gutesio_data_child');
        System::loadLanguageFile('offer_list');
        $isContao5 = C4GVersionProvider::isContaoVersionAtLeast('5.0.0');
        foreach ($childRows as $key => $row) {
            $tooOld = false;

            // TODO
            if (key_exists($row['uuid'], $this->cachedAdditionalData)) {
                $childRows[$key] = $this->cachedAdditionalData[$row['uuid']];
                continue;
            }

            switch ($row['type']) {
                case 'product':
                    $productData = $database->prepare(
                        'SELECT p.price, p.strikePrice, p.priceStartingAt, p.priceReplacer, '.
                        'p.tax as taxNote, p.discount, p.color, p.size, p.availableAmount, p.basePriceUnit, p.basePriceUnitPerPiece, p.basePriceRequired, '.
                        'p.allergenes, p.ingredients, p.kJ, p.fat, p.saturatedFattyAcid, p.carbonHydrates, p.sugar, p.salt, '.
                        'p.isbn, p.ean, p.brand '.
                        'FROM tl_gutesio_data_child_product p WHERE p.childId = ?'
                    )->execute($row['uuid'])->fetchAssoc();
                    if (!empty($productData)) {
                        $productData['rawPrice'] = $productData['price'];
                        if ($productData['strikePrice'] > 0 && $productData['strikePrice'] > $productData['price']) {
                            $productData['strikePrice'] =
                                number_format(
                                    $productData['strikePrice'] ?: 0,
                                    2,
                                    ',',
                                    ''
                                ) . ' €*';
                            if ($productData['priceStartingAt']) {
                                $productData['strikePrice'] =
                                    $GLOBALS['TL_LANG']['offer_list']['frontend']['startingAt'] .
                                    ' ' . $productData['strikePrice'];
                            }
                        } else {
                            unset($productData['strikePrice']);
                        }
                        if (!empty($productData['priceReplacer'])) {
                            $productData['price'] =
                                $GLOBALS['TL_LANG']['offer_list']['price_replacer_options'][$productData['priceReplacer']];
                        } elseif ((!$productData['price'])) {
                            $productData['price'] =
                                $GLOBALS['TL_LANG']['offer_list']['price_replacer_options']['free'];
                        } else {
                            $productData['price'] =
                                number_format(
                                    $productData['price'] ?: 0,
                                    2,
                                    ',',
                                    ''
                                ) . ' €';
                            if ($productData['price'] > 0) {
                                $productData['price'] .= '*';
                            }
                            if ($productData['priceStartingAt']) {
                                $productData['price'] =
                                    $GLOBALS['TL_LANG']['offer_list']['frontend']['startingAt'] .
                                    ' ' . $productData['price'];
                            }
                        }

                        $productData['color'] = $productData['color'] ?: '';
                        $productData['size'] = $productData['size'] ?: '';
                        $productData['isbn'] = $productData['isbn'] ?: '';
                        $productData['ean'] = $productData['ean'] ?: '';
                        $productData['brand'] = $productData['brand'] ?: '';
                        $productData['basePriceUnit'] = $productData['basePriceUnit'] ?: '';
                        $productData['basePriceUnitPerPiece'] = $productData['basePriceUnitPerPiece'] ?: '';
                        $productData['basePriceRequired'] = $productData['basePriceRequired'] ?: false;
                        $productData['availableAmount'] = $productData['availableAmount'] ?: '';

                        if ($productData['basePriceRequired']) {
                            $productData['basePrice'] = $productData['rawPrice'] && $productData['size'] && $productData['basePriceUnitPerPiece'] ? $productData['rawPrice'] / $productData['size'] * $productData['basePriceUnitPerPiece'] : '';
                            $productData['basePrice'] = number_format(
                                    $productData['basePrice'] ?: 0,
                                    2,
                                    ',',
                                    ''
                                ) . ' €';
                        }
                        $productData['allergenes'] = $productData['allergenes'] ?: '';
                        $productData['ingredients'] = $productData['ingredients'] ?: '';
                        $productData['kJ'] = $productData['kJ'] ?: '';
                        $productData['fat'] = $productData['fat'] ?: '';
                        $productData['saturatedFattyAcid'] = $productData['saturatedFattyAcid'] ?: '';
                        $productData['carbonHydrates'] = $productData['carbonHydrates'] ?: '';
                        $productData['sugar'] = $productData['sugar'] ?: '';
                        $productData['salt'] = $productData['salt'] ?: '';

                        $settings = GutesioOperatorSettingsModel::findSettings();
                        switch ($productData['taxNote']) {
                            case 'regular':
                                $productData['taxNote'] = sprintf(
                                    $GLOBALS['TL_LANG']['offer_list']['frontend']['details']['taxInfo'],
                                    ($settings->taxRegular ?: '19') . '%'
                                );

                                break;
                            case 'reduced':
                                $productData['taxNote'] = sprintf(
                                    $GLOBALS['TL_LANG']['offer_list']['frontend']['details']['taxInfo'],
                                    ($settings->taxReduced ?: '7') . '%'
                                );

                                break;
                            case 'none':
                                $productData['taxNote'] =
                                    $GLOBALS['TL_LANG']['offer_list']['frontend']['details']['noTaxInfo'];

                                break;
                            default:
                                $productData['taxNote'] =
                                    $GLOBALS['TL_LANG']['offer_list']['frontend']['list']['taxInfo'];

                                break;
                        }
                        $childRows[$key] = array_merge($row, $productData);
                    }

                    break;

                case 'job':
                    $jobData = $database->prepare('SELECT beginDate AS beginDate ' .
                        'FROM tl_gutesio_data_child_job ' .
                        'JOIN tl_gutesio_data_child ON tl_gutesio_data_child_job.childId = tl_gutesio_data_child.uuid ' .
                        'WHERE childId = ?')
                        ->execute($row['uuid'])->fetchAssoc();

                    if (!empty($jobData)) {
                        if ((string) $jobData['beginDate'] === '') {
                            $jobData['beginDate'] = 'ab sofort';
                        } else {
                            $jobData['beginDate'] = date('d.m.Y', $jobData['beginDate']);
                        }
                        $childRows[$key] = array_merge($row, $jobData);
                    }

                    break;

                case 'person':
                    $personData = $database->prepare('SELECT dateOfBirth, dateOfDeath ' .
                        'FROM tl_gutesio_data_child_person ' .
                        'JOIN tl_gutesio_data_child ON tl_gutesio_data_child_person.childId = tl_gutesio_data_child.uuid ' .
                        'WHERE childId = ?')
                        ->execute($row['uuid'])->fetchAssoc();

                    if (!empty($personData)) {
                        $childRows[$key] = array_merge($row, $personData);
                    }

                    break;

                case 'voucher':
                    $voucherData = $database->prepare('SELECT minCredit, maxCredit, credit, customizableCredit ' .
                        'FROM tl_gutesio_data_child_voucher ' .
                        'JOIN tl_gutesio_data_child ON tl_gutesio_data_child_voucher.childId = tl_gutesio_data_child.uuid ' .
                        'WHERE childId = ?')
                        ->execute($row['uuid'])->fetchAssoc();

                    if (!empty($voucherData)) {
                        $childRows[$key] = array_merge($row, $voucherData);
                    }

                    break;
                default:
                    break;
            }


            $vendorUuid = $database->prepare(
                'SELECT * FROM tl_gutesio_data_child_connection WHERE childId = ? LIMIT 1'
            )->execute($row['uuid'])->fetchAssoc();

            $vendor = $database->prepare(
                'SELECT * FROM tl_gutesio_data_element WHERE uuid = ?'
            )->execute($vendorUuid['elementId'])->fetchAssoc();

            if ($vendor && count($vendor) && $vendor['name'] && $vendor['alias']) {
                $childRows[$key]['elementName'] = html_entity_decode($vendor['name']);

                //hotfix special char
                $childRows[$key]['elementName'] = str_replace('&#39;', "'", $childRows[$key]['elementName']);

                $objSettings = GutesioOperatorSettingsModel::findSettings();
                $elementPage = PageModel::findByPk($objSettings->showcaseDetailPage);
                if ($elementPage !== null) {
                    if ($isContao5) {
                        $url = $elementPage->getAbsoluteUrl(['parameters' => "/" . $vendor['alias']]);
                    } else {
                        $url = $elementPage->getAbsoluteUrl();
                    }

                    if ($url) {

                        if (str_ends_with($url, $vendor['alias'])) {
                            $href = $url;
                        } else {
                            $href = '';
                            if (C4GUtils::endsWith($url, '.html')) {
                                $href = str_replace('.html', '/' . strtolower(str_replace(['{', '}'], '', $vendor['alias'])) . '.html', $url);
                            } else if ($vendor['alias']) {
                                $href = $url . '/' . strtolower(str_replace(['{', '}'], '', $vendor['alias']));
                            }
                        }

                        $childRows[$key]['elementLink'] = $href ?: '';
                    }
                }
                $childPage = match ($row['type']) {
                    'product' => PageModel::findByPk($objSettings->productDetailPage),
                    'jobs' => PageModel::findByPk($objSettings->jobDetailPage),
                    'event' => PageModel::findByPk($objSettings->eventDetailPage),
                    'arrangement' => PageModel::findByPk($objSettings->arrangementDetailPage),
                    'service' => PageModel::findByPk($objSettings->serviceDetailPage),
                    'person' => PageModel::findByPk($objSettings->personDetailPage),
                    'voucher' => PageModel::findByPk($objSettings->voucherDetailPage),
                    default => null,
                };

                if ($childPage !== null) {
                    if (C4GVersionProvider::isContaoVersionAtLeast("5.0")) {
                        $objRouter = System::getContainer()->get('contao.routing.content_url_generator');
                        $url = $objRouter->generate($childPage, ['parameters' => "/" . $row['uuid']]);
                    } else {
                        $url = $childPage->getAbsoluteUrl();
                    }

                    if ($url) {
                        if (C4GUtils::endsWith($url, '.html')) {
                            $href = str_replace('.html', '/' . strtolower(str_replace(['{', '}'], '', $vendor['alias'])) . '.html', $url);
                        } else {
                            $href = $url . '/' . strtolower(str_replace(['{', '}'], '', $row['uuid']));
                        }
                        if (str_ends_with($href, "/href")) {
                            $href = str_replace("/href", "", $href);
                        }
                        $childRows[$key]['childLink'] = $href ?: '';
                    }
                }

                $this->addAdditionalDataToCache($childRows[$key]['uuid'], $childRows[$key]);
            }
        }

        return array_values($childRows);
    }

    public function getElementData($childId)
    {
        $service = ShowcaseService::getInstance();
        $result = $service->loadByChildId($childId);

        foreach ($result as $key => $row) {
            if ($row['types']) {
                $result[$key]['types'] = implode(', ', array_column($row['types'], 'label'));
            }
        }

        return $result;
    }

    public function applyTagFilter($data, $tagIds, $offset, $limit)
    {
        $result = [];
        $db = Database::getInstance();

        $tagString = C4GUtils::buildInString($tagIds);
        foreach ($data as $datum) {
            $sql = 'SELECT * FROM tl_gutesio_data_child_tag WHERE `childId` = ? AND `tagId` ' . $tagString;
            $params = array_merge([$datum['uuid']], $tagIds);
            $tagChildConnections = $db->prepare($sql)->execute($params)->fetchAllAssoc();
            if (count($tagChildConnections) > 0) {
                $result[] = $datum;
            }
        }

        return $result;
    }

    public function applyCategoryFilter($data, $categoryIds, $offset, $limit)
    {
        $result = [];
        $db = Database::getInstance();
        $categoryString = C4GUtils::buildInString($categoryIds);
        foreach ($data as $datum) {
            $sql = 'SELECT * FROM tl_gutesio_data_child WHERE `uuid` = ? AND `typeId` ' . $categoryString;
            $params = array_merge([$datum['uuid']], $categoryIds);
            $categoryChildConnections = $db->prepare($sql)->execute(...$params)->fetchAllAssoc();
            if (count($categoryChildConnections) > 0) {
                $result[] = $datum;
            }
        }

        return $result;
    }

    private function applyRangeFilter(array $data, int $filterFrom, int $filterUntil)
    {
        $result = [];
        $appendList = [];
        foreach ($data as $datum) {
            if ($datum['type'] !== 'event') {
                $appendList[] = $datum;
            } else {
                if ($datum['appointmentUponAgreement']) {
                    $result[] = $datum;
                } else {
                    // add one day so events are displayed on the day they expire
                    $beginTstamp = $datum['beginDate'];
                    if (!is_integer($beginTstamp)) {
                        $beginTstamp = strtotime($beginTstamp);
                    }
                    $endTstamp = $datum['endDate'];

                    if (!is_integer($endTstamp)) {
                        $endTstamp = strtotime($endTstamp);
                    }


                    if ($filterFrom) {
                        $fromDt = (new \DateTime())->setTimestamp($filterFrom);
                        $filterFromFirstMinute = $fromDt->setTime(0, 0, 0)->getTimestamp();
                    }
                    if ($filterUntil) {
                        $untilDt = (new \DateTime())->setTimestamp($filterUntil);
                        $filterUntilLastMinute = $untilDt->setTime(23, 59, 59)->getTimestamp();
                    }

                    $dateMatchesFilter = ($filterFromFirstMinute == NULL && $filterUntilLastMinute == NULL) ? 1 : false;
                    $dateMatchesFilter = !$dateMatchesFilter && ($filterFromFirstMinute && $filterUntilLastMinute == NULL && ($beginTstamp >= $filterFromFirstMinute)) ? 2 : $dateMatchesFilter;
                    $dateMatchesFilter = !$dateMatchesFilter && ($filterFromFirstMinute && $filterUntilLastMinute == NULL && $endTstamp && ($endTstamp >= $filterFromFirstMinute)) ? 3 : $dateMatchesFilter;
                    $dateMatchesFilter = !$dateMatchesFilter && ($filterFromFirstMinute && $filterUntilLastMinute && !$endTstamp && ($beginTstamp >= $filterFromFirstMinute) && ($beginTstamp <= $filterUntilLastMinute)) ? 4 : $dateMatchesFilter;
                    $dateMatchesFilter = !$dateMatchesFilter && ($filterFromFirstMinute && $filterUntilLastMinute && $endTstamp && ($beginTstamp >= $filterFromFirstMinute) && ($endTstamp <= $filterUntilLastMinute)) ? 5 : $dateMatchesFilter;
                    $dateMatchesFilter = !$dateMatchesFilter && ($filterFromFirstMinute == NULL && $filterUntilLastMinute && ($beginTstamp <= $filterUntilLastMinute)) ? 6 : $dateMatchesFilter;
                    $dateMatchesFilter = !$dateMatchesFilter && ($filterFromFirstMinute == NULL && $filterUntilLastMinute && $endTstamp && ($endTstamp <= $filterUntilLastMinute))? 7 : $dateMatchesFilter;

                    if (!$dateMatchesFilter) {
                        if ($beginTstamp <= $filterFromFirstMinute && ($filterUntilLastMinute && ($filterUntilLastMinute <= $endTstamp))) {
                            $dateMatchesFilter = true;
                        }
                    }

                    if (str_contains($datum['name'], "Frauenzimmer")) {
                        $datum['datematchesfilter'] = $dateMatchesFilter;
//                        dd($datum);
                    }

                    if ($dateMatchesFilter) {
                        $result[] = $datum;
                    }
                }
            }
        }

        usort($result, function ($a, $b) {
            $a['storeBeginTime'] = $a['storeBeginTime'] ?: strtotime($a['beginTime']);
            $b['storeBeginTime'] = $b['storeBeginTime'] ?: strtotime($b['beginTime']);
            $a['storeBeginDate'] = $a['storeBeginDate'] ?: strtotime($a['beginDate']);
            $b['storeBeginDate'] = $b['storeBeginDate'] ?: strtotime($b['beginDate']);

            $aTstamp = $a['storeBeginTime'] ? $a['storeBeginDate'] + $a['storeBeginTime'] : $a['storeBeginDate'];
            $bTstamp = $b['storeBeginTime'] ? $b['storeBeginDate'] + $b['storeBeginTime'] : $b['storeBeginDate'];
            if ($aTstamp < $bTstamp) {
                return -1;
            } elseif ($aTstamp > $bTstamp) {
                return 1;
            }

            return 0;
        });

        $result = array_merge($result, $appendList);

        return $result;
    }

    private function loadFromCache()
    {
        $cache = OfferDataCache::getInstance('../var/cache/prod/con4gis');

        if ($cache->hasCacheData("offerTags")) {
            $cachedData = $cache->getCacheData("offerTags");
            if ($cachedData) {
                $this->cachedTagData = StringUtil::deserialize($cachedData, true);
            }
        }

        if ($cache->hasCacheData("offerAdditionalData")) {
            $cachedData = $cache->getCacheData("offerAdditionalData");
            if ($cachedData) {
                $this->cachedAdditionalData = StringUtil::deserialize($cachedData, true);
            }
        }
    }

    private function addAdditionalDataToCache($childUuid, $additionalData)
    {
        $this->newCacheAdditionalData[$childUuid] = $additionalData;
    }

    private function addTagDataToCache($childUuid, $tagData)
    {
        $this->newCacheTagData[$childUuid] = $tagData;
    }

    private function writeOfferDataToCache()
    {
        $cache = OfferDataCache::getInstance('../var/cache/prod/con4gis');

        $cacheData = array_merge($this->cachedTagData, $this->newCacheTagData);
        $cache->putCacheData("offerTags", $cacheData);

        $cacheData = array_merge($this->cachedAdditionalData, $this->newCacheAdditionalData);
        $cache->putCacheData("offerAdditionalData", $cacheData);
    }

    /*
     * Utility functions
     */

    private function createRandomSeed(Request $request)
    {
        $this->randomSeed = (int) rand(0, 99999);
    }

    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request->cookies->get('clientUuid');

        return $clientUuidCookie === null ? null : $clientUuidCookie;
    }

    /**
     * Strips the curly braces and converts $alias to lowercase, if needed.
     * @param string $alias
     */
    private function cleanAlias(string $alias)
    {
        $containsCurlyBraces = (strpos($alias, '{') !== false)
            || (strpos($alias, '}') !== false);
        if ($containsCurlyBraces) {
            $alias = str_replace(['{', '}'], ['', ''], $alias);
        }

        return strtoupper($alias);
    }

    /**
     * @return ModuleModel
     */
    public function getModel(): ?ModuleModel
    {
        return $this->model;
    }

    /**
     * @param ModuleModel $model
     */
    public function setModel(?ModuleModel $model): void
    {
        $this->model = $model;
    }

    /**
     * @return string
     */
    public function getPageUrl(): string
    {
        return $this->pageUrl;
    }

    /**
     * @param string $pageUrl
     */
    public function setPageUrl(string $pageUrl): void
    {
        $this->pageUrl = $pageUrl;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
}
