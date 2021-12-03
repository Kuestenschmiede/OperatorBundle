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
use con4gis\FrameworkBundle\Classes\Utility\RegularExpression;
use Contao\Controller;
use Contao\Database;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use gutesio\DataModelBundle\Classes\ChildFullTextContentUpdater;
use gutesio\DataModelBundle\Classes\TagDetailFieldGenerator;
use gutesio\DataModelBundle\Classes\TagFieldUtil;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataElementModel;
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

    /**
     * @var VisitCounterService
     */
    private $visitCounter = null;

    /**
     * OfferLoaderService constructor.
     */
    public function __construct()
    {
        $this->visitCounter = new VisitCounterService();
    }

    private function setup()
    {
        $this->createRandomSeed($this->request);
    }

    public function getListData($search, $offset, $type, $filterData)
    {
        $this->setup();
        $limit = $this->limit;
        $tagIds = $filterData['tagIds'];
        $tagFilter = $tagIds && count($tagIds) > 0;
        $dateFilter = $filterData['filterFrom'] || $filterData['filterUntil'];
        $sortFilter = $filterData['sorting'];
        $hasFilter = $tagFilter || $sortFilter || $dateFilter;
        if ($hasFilter) {
            // raise limit and ignore offset temporarily
            $limit = 5000;
            $tmpOffset = $offset;
            $offset = 0;
        }

        if ($search !== '') {
            $terms = explode(' ', $search);
            $results = $this->getFullTextData($terms, $offset, $type, $limit, $dateFilter);
        } else {
            $results = $this->getFullTextDataWithoutTerms($offset, $type, $limit, $dateFilter);
        }
        if ($tagFilter) {
            // filter using actual limit & offset
            $results = $this->applyTagFilter($results, $tagIds, $tmpOffset, $this->limit);
        }

        if ($dateFilter) {
            $results = $this->applyRangeFilter($results, $filterData['filterFrom'] ?: 0, $filterData['filterUntil'] ?: 0);
        }
        $results = $this->sortOfferData($sortFilter, $filterData, $results);
        if ($hasFilter) {
            $results = array_slice($results, $tmpOffset, $this->limit);
        }

        // data cleaning
        foreach ($results as $key => $result) {
            $results[$key]['shortDescription'] = html_entity_decode($result['shortDescription']);
            if ($result['foreignLink']) {
                // search for http to avoid prepending https to insecure links
                if (strpos($result['foreignLink'], 'http') === false) {
                    $results[$key]['foreignLink'] = C4GUtils::addProtocolToLink($result['foreignLink']);
                }
            }
        }

        return $results;
    }

    private function sortOfferData(string $sortFilter, array $filterData, array $offers)
    {
        if ($sortFilter && $sortFilter !== 'random') {
            if ($sortFilter === 'date') {
                $dateOffers = [];
                $noDateOffers = [];
                foreach ($offers as $offer) {
                    if ($offer['beginDate']) {
                        $dateOffers[] = $offer;
                    } else {
                        $noDateOffers[] = $offer;
                    }
                }
                usort($dateOffers, function ($a, $b) use ($sort) {
                    $aDate = strtotime($a['beginDate']);
                    $bDate = strtotime($b['beginDate']);
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
            } else {
                if ($filterData['sorting'] === 'price_asc') {
                    $sort = 'asc';
                } else {
                    $sort = 'desc';
                }
                usort($offers, function ($a, $b) use ($sort) {
                    $aPrice = $a['rawPrice'];
                    $bPrice = $b['rawPrice'];
                    if ($aPrice === null) {
                        return 1;
                    }
                    if ($bPrice === null) {
                        return -1;
                    }
                    if ($sort === 'asc') {
                        if ($aPrice > $bPrice) {
                            return 1;
                        } elseif ($aPrice < $bPrice) {
                            return -1;
                        }

                        return 0;
                    }
                    if ($aPrice > $bPrice) {
                        return -1;
                    } elseif ($aPrice < $bPrice) {
                        return 1;
                    }

                    return 0;
                });
            }
        }

        return $offers;
    }

    public function getFullTextData(
        array $terms,
        $offset = 0,
        string $type = '',
        int $limit = 0,
        bool $dateFilter = false
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

        $arrTagFieldClause = $this->createTagFieldClause();
        $strTagFieldClause = $arrTagFieldClause['str'];
        $fieldCount = $arrTagFieldClause['count'];
        $sqlExtendedCategoryTerms = ' OR tl_gutesio_data_child_type.extendedSearchTerms LIKE ?';
        $fieldCount++;

        if (empty($types) && empty($categories)) {
            if (!empty($tags)) {
                $parameters = $tags;
                for ($i = 0; $i < $fieldCount; $i++) {
                    $parameters[] = '%' . $rawTermString . '%';
                }
                $parameters[] = $limit;
                $parameters[] = (int) $offset;
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.image, a.imageOffer, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) as relevance, ' . '
                a.uuid as alias ' . '
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
                    ' ORDER BY relevance DESC LIMIT ? OFFSET ?'
                )->execute($parameters)->fetchAllAssoc();
            } else {
                $parameters = [];
                for ($i = 0; $i < $fieldCount; $i++) {
                    $parameters[] = '%' . $rawTermString . '%';
                }
                $parameters[] = $limit;
                $parameters[] = (int) $offset;
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.image, a.imageOffer, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) as relevance, ' . '
                a.uuid as alias ' . '
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
                ORDER BY relevance DESC LIMIT ? OFFSET ?')
                    ->execute(
                        $parameters
                    )->fetchAllAssoc();
            }
        } elseif (empty($categories)) {
            $parameters = $types;
            for ($i = 0; $i < $fieldCount; $i++) {
                $parameters[] = '%' . $rawTermString . '%';
            }
            $parameters[] = $limit;
            $parameters[] = (int) $offset;
            $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                'a.tstamp, a.typeId, a.name, a.image, a.imageOffer, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) as relevance, ' . '
                a.uuid as alias ' . '
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
                ' ORDER BY relevance DESC LIMIT ? OFFSET ?'
            )->execute($parameters)->fetchAllAssoc();
        } else {
            $parameters = $categories;
            for ($i = 0; $i < $fieldCount; $i++) {
                $parameters[] = '%' . $rawTermString . '%';
            }
            $parameters[] = $limit;
            $parameters[] = (int) $offset;
            $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                'a.tstamp, a.typeId, a.name, a.image, a.imageOffer, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                match(a.fullTextContent) against(\'' . $termString . '\' in boolean mode) as relevance, ' . '
                a.uuid as alias ' . '
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
                ' ORDER BY relevance DESC LIMIT ? OFFSET ?'
            )->execute($parameters)->fetchAllAssoc();
        }

        foreach ($childRows as $key => $row) {
            $imageModel = $row['imageOffer'] && FilesModel::findByUuid($row['imageOffer']) ? FilesModel::findByUuid($row['imageOffer']) : FilesModel::findByUuid($row['image']);
            if ($imageModel !== null) {
                list($width, $height) = getimagesize($imageModel->path);
                $childRows[$key]['image'] = [
                    'src' => $imageModel->path,
                    'alt' => $imageModel->meta && unserialize($imageModel->meta)['de'] ? unserialize($imageModel->meta)['de']['alt'] : $row['name'],
                    'width' => $width,
                    'height' => $height,
                ];
            }
            unset($childRows[$key]['imageOffer']);

            $childRows[$key]['href'] = strtolower(str_replace(['{', '}'], ['', ''], $row['uuid']));

            $childRows = $this->getTagData($row['uuid'], $childRows, $key);
        }

        return $this->getAdditionalData($childRows, $dateFilter);
    }

    public function getFullTextDataWithoutTerms(
        $offset = 0,
        string $type = '',
        int $limit = 0,
        bool $dateFilter = false
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

        if (empty($types) && empty($categories)) {
            if (!empty($tags)) {
                $parameters = $tags;
                $parameters[] = $limit;
                $parameters[] = (int) $offset;
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.image, a.imageOffer, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                a.uuid as alias ' . '
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
                    ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                    ' ORDER BY RAND(' . $this->randomSeed . ') LIMIT ? OFFSET ?'
                )->execute($parameters)->fetchAllAssoc();
            } else {
                $parameters = [];
                $parameters[] = $limit;
                $parameters[] = (int) $offset;
                $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                    'a.tstamp, a.typeId, a.name, a.image, a.imageOffer, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                a.uuid as alias ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                WHERE a.published = 1 AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())
                ORDER BY RAND(' . $this->randomSeed . ') LIMIT ? OFFSET ?')
                    ->execute(
                        $parameters
                    )->fetchAllAssoc();
            }
        } elseif (empty($categories)) {
            $parameters = $types;
            $parameters[] = $limit;
            $parameters[] = (int) $offset;
            $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                'a.tstamp, a.typeId, a.name, a.image, a.imageOffer, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                a.uuid as alias ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                WHERE a.published = 1 AND tl_gutesio_data_child_type.type ' . C4GUtils::buildInString($types) .
                ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                ' ORDER BY RAND(' . $this->randomSeed . ') LIMIT ? OFFSET ?'
            )->execute($parameters)->fetchAllAssoc();
        } else {
            $parameters = $categories;
            $parameters[] = $limit;
            $parameters[] = (int) $offset;
            $childRows = $database->prepare('SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
                'a.tstamp, a.typeId, a.name, a.image, a.imageOffer, a.foreignLink, a.directLink, a.offerForSale, ' . '
                (CASE ' . '
                    WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                    WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                    WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                    WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
                ELSE NULL END) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                a.uuid as alias ' . '
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
                LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
                LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                WHERE a.published = 1 AND a.typeId ' . C4GUtils::buildInString($categories) .
                ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())' .
                ' ORDER BY RAND(' . $this->randomSeed . ') LIMIT ? OFFSET ?'
            )->execute($parameters)->fetchAllAssoc();
        }

        foreach ($childRows as $key => $row) {
            $imageModel = $row['imageOffer'] && FilesModel::findByUuid($row['imageOffer']) ? FilesModel::findByUuid($row['imageOffer']) : FilesModel::findByUuid($row['image']);
            if ($imageModel !== null) {
                list($width, $height) = getimagesize($imageModel->path);
                $childRows[$key]['image'] = [
                    'src' => $imageModel->path,
                    'alt' => $imageModel->meta && unserialize($imageModel->meta)['de'] ? unserialize($imageModel->meta)['de']['alt'] : $row['name'],
                    'height' => $height,
                    'width' => $width,
                ];
            } else {
                unset($childRows[$key]['image']);
            }
            unset($childRows[$key]['imageOffer']);

            $childRows[$key]['href'] = strtolower(str_replace(['{', '}'], ['', ''], $row['uuid']));

            $childRows = $this->getTagData($row['uuid'], $childRows, $key);
        }

        return $this->getAdditionalData($childRows, $dateFilter);
    }

    public function getDetailData($alias)
    {
        $dataset = $this->getSingleDataset($alias, true);
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

    public function getSingleDataset($alias, $published, $isPreview = false)
    {
        $database = Database::getInstance();
        $alias = $this->cleanAlias($alias);

        $sql = 'SELECT DISTINCT a.id, a.parentChildId, a.uuid, a.tstamp, a.typeId, ' . '
            a.name, a.image, a.imageOffer, a.imageGallery, a.memberId, a.infoFile,' . '
            (CASE ' . '
                WHEN a.description IS NOT NULL THEN a.description ' . '
                WHEN b.description IS NOT NULL THEN b.description ' . '
                WHEN c.description IS NOT NULL THEN c.description ' . '
                WHEN d.description IS NOT NULL THEN d.description ' . '
            ELSE NULL END) AS description, ' . '
            tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, tl_gutesio_data_child_type.extendedSearchTerms as extendedSearchTerms FROM tl_gutesio_data_child a ' . '
            LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
            LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
            LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
            LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
            LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
            JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
            WHERE a.uuid = ?';
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
            $rows[$key]['websiteLabel'] = $result['websiteLabel'];
            $rows[$key]['opening_hours'] = html_entity_decode($result['opening_hours']);
            $rows[$key]['deviatingPhoneHours'] = $result['deviatingPhoneHours'];
            $rows[$key]['phoneHours'] = html_entity_decode($result['phoneHours']);
            $rows[$key]['opening_hours'] = html_entity_decode($result['opening_hours']);
            $rows[$key]['opening_hours_additional'] = html_entity_decode($result['opening_hours_additional']);
            $rows[$key]['contactName'] = html_entity_decode($result['contactName']);
            $rows[$key]['contactAdditionalName'] = html_entity_decode($result['contactAdditionalName']);
            $rows[$key]['contactStreet'] = $result['contactStreet'];
            $rows[$key]['contactStreetNumber'] = $result['contactStreetNumber'];
            $rows[$key]['contactZip'] = $result['contactZip'];
            $rows[$key]['contactCity'] = $result['contactCity'];
            $rows[$key]['locationName'] = $result['locationName'];
            $rows[$key]['locationAdditionalName'] = $result['locationAdditionalName'];
            $rows[$key]['locationStreet'] = $result['locationStreet'];
            $rows[$key]['locationStreetNumber'] = $result['locationStreetNumber'];
            $rows[$key]['locationZip'] = $result['locationZip'];
            $rows[$key]['locationCity'] = $result['locationCity'];
            $rows[$key]['geox'] = $result['geox'];
            $rows[$key]['geoy'] = $result['geoy'];
            $rows[$key]['elementId'] = $result['uuid'];

            if ($row['infoFile']) {
                $infoFile = FilesModel::findByUuid(StringUtil::binToUuid($row['infoFile']));
                if ($infoFile !== null) {
                    $rows[$key]['infoFile'] = [
                        'name' => $infoFile->name,
                        'path' => $infoFile->path,
                        'changed' => false,
                        'data' => [],
                    ];
                } else {
                    unset($rows[$key]['infoFile']);
                }
            }

            if ($row['imageGallery']) {
                $images = StringUtil::deserialize($row['imageGallery']);
                if ($isPreview) {
                    array_unshift($images, StringUtil::binToUuid($rows[$key]['image']));
                }
                $idx = 0;
                foreach ($images as $image) {
                    $model = FilesModel::findByUuid(StringUtil::deserialize($image));
                    if ($model !== null) {
                        $size = getimagesize($model->path);
                        $rows[$key]['imageGallery_' . $idx] = [
                            'src' => $model->path,
                            'path' => $model->path,
                            'uuid' => StringUtil::binToUuid($model->uuid),
                            'alt' => $model->meta && unserialize($model->meta)['de'] ? unserialize($model->meta)['de']['alt'] : $model->name,
                            'name' => $model->name,
                            'width' => $size[0],
                            'height' => $size[1],
                            'importantPart' => [
                                'x' => $model->importantPartX,
                                'y' => $model->importantPartY,
                                'width' => $model->importantPartWidth,
                                'height' => $model->importantPartHeight,
                            ],
                        ];
                        $idx++;
                    }
                }
                unset($rows[$key]['imageGallery']);
            }

            unset($rows[$key]['imageOffer'], $rows[$key]['image']);

            $tagStmt = $database->prepare(
                'SELECT tl_gutesio_data_tag.* FROM tl_gutesio_data_tag JOIN tl_gutesio_data_child_tag ON ' .
                'tl_gutesio_data_child_tag.tagId = tl_gutesio_data_tag.uuid WHERE tl_gutesio_data_tag.published = 1' .
                ' AND tl_gutesio_data_child_tag.childId = ? AND (tl_gutesio_data_tag.validFrom = 0' .
                ' OR tl_gutesio_data_tag.validFrom IS NULL' .
                ' OR tl_gutesio_data_tag.validFrom >= UNIX_TIMESTAMP() AND (tl_gutesio_data_tag.validUntil = 0' .
                ' OR tl_gutesio_data_tag.validUntil IS NULL' .
                ' OR tl_gutesio_data_tag.validUntil <= UNIX_TIMESTAMP()))'
            );
            $rows[$key]['tags'] = $tagStmt->execute($rows[$key]['uuid'])->fetchAllAssoc();

            foreach ($rows[$key]['tags'] as $tagKey => $tagRow) {
                $imageModel = FilesModel::findByUuid($tagRow['image']);
                if ($imageModel !== null) {
                    $rows[$key]['tags'][$tagKey]['image'] = [
                        'alt' => $tagRow['name'],
                        'importantPart' => [
                            'x' => $imageModel->importantPartX,
                            'y' => $imageModel->importantPartY,
                            'width' => $imageModel->importantPartWidth,
                            'height' => $imageModel->importantPartHeight,
                        ],
                        'name' => $imageModel->name,
                        'path' => $imageModel->path,
                        'src' => $imageModel->path,
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

            $rows = $this->getTagData($row['uuid'], $rows, $key);
        }

        $rows = $this->getAdditionalData($rows, false, !$isPreview);

        return $rows[0];
    }

    private function getTagData($uuid, $childRows, $key)
    {
        $database = Database::getInstance();

        $result = $database->prepare('SELECT name, image, technicalKey FROM tl_gutesio_data_tag ' .
            'JOIN tl_gutesio_data_child_tag ON tl_gutesio_data_tag.uuid = tl_gutesio_data_child_tag.tagId ' .
            'WHERE tl_gutesio_data_tag.published = 1 AND (tl_gutesio_data_tag.validFrom = 0' .
            ' OR tl_gutesio_data_tag.validFrom IS NULL' .
            ' OR tl_gutesio_data_tag.validFrom >= UNIX_TIMESTAMP() AND (tl_gutesio_data_tag.validUntil = 0' .
            ' OR tl_gutesio_data_tag.validUntil IS NULL' .
            ' OR tl_gutesio_data_tag.validUntil <= UNIX_TIMESTAMP())) AND tl_gutesio_data_child_tag.childId = ?')
            ->execute($uuid)->fetchAllAssoc();

        foreach ($result as $r) {
            $model = FilesModel::findByUuid($r['image']);
            if ($model !== null) {
                $icon = [
                    'name' => $r['name'],
                    'image' => [
                        'src' => $model->path,
                        'alt' => $r['name'],
                        'width' => 100,
                        'height' => 100,
                    ],
                ];
                switch ($r['technicalKey']) {
                    case 'tag_delivery':
                        $stmt = $database->prepare(
                            'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                            'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                        $tagLink = $stmt->execute(
                            $uuid,
                            'deliveryServiceLink'
                        )->fetchAssoc()['tagFieldValue'];
                        $icon['linkHref'] = C4GUtils::addProtocolToLink($tagLink);

                        break;
                    case 'tag_online_reservation':
                        $stmt = $database->prepare(
                            'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                            'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                        $tagLink = $stmt->execute(
                            $uuid,
                            'onlineReservationLink'
                        )->fetchAssoc()['tagFieldValue'];

                        if (preg_match('/' . RegularExpression::EMAIL . '/', $tagLink)) {
                            if (strpos($tagLink, 'mailto:') !== 0) {
                                $tagLink = 'mailto:' . $tagLink;
                            }
                        } else {
                            $tagLink = C4GUtils::addProtocolToLink($tagLink);
                        }

                        $icon['linkHref'] = $tagLink;

                        break;
                    case 'tag_clicknmeet':
                        $stmt = $database->prepare(
                            'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                            'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                        $tagLink = $stmt->execute(
                            $uuid,
                            'clicknmeetLink'
                        )->fetchAssoc()['tagFieldValue'];
                        $icon['linkHref'] = C4GUtils::addProtocolToLink($tagLink);

                        break;
                    case 'tag_table_reservation':
                        $stmt = $database->prepare(
                            'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                            'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                        $tagLink = $stmt->execute(
                            $uuid,
                            'tableReservationLink'
                        )->fetchAssoc()['tagFieldValue'];
                        $icon['linkHref'] = C4GUtils::addProtocolToLink($tagLink);

                        break;
                    case 'tag_onlineshop':
                        $stmt = $database->prepare(
                            'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                            'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                        $tagLink = $stmt->execute(
                            $uuid,
                            'onlineShopLink'
                        )->fetchAssoc()['tagFieldValue'];
                        $icon['linkHref'] = C4GUtils::addProtocolToLink($tagLink);

                        break;
                    default:
                        break;
                }
                $icon['class'] .= $r['technicalKey'];

                if (!$childRows[$key]['tagLinks']) {
                    $childRows[$key]['tagLinks'] = [];
                }

                $childRows[$key]['tagLinks'][] = $icon;
            }
        }

        return $childRows;
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
        foreach ($childRows as $key => $row) {
            $tooOld = false;
            switch ($row['type']) {
                case 'product':
                    $productData = $database->prepare('SELECT ' . '
                        (CASE ' . '
                            WHEN a.price IS NOT NULL THEN a.price ' . '
                            WHEN b.price IS NOT NULL THEN b.price ' . '
                            WHEN c.price IS NOT NULL THEN c.price ' . '
                            WHEN d.price IS NOT NULL THEN d.price ' . '
                        ELSE NULL END) AS price, ' . '
                        (CASE ' . '
                            WHEN a.strikePrice IS NOT NULL THEN a.strikePrice ' . '
                            WHEN b.strikePrice IS NOT NULL THEN b.strikePrice ' . '
                            WHEN c.strikePrice IS NOT NULL THEN c.strikePrice ' . '
                            WHEN d.strikePrice IS NOT NULL THEN d.strikePrice ' . '
                        ELSE NULL END) AS strikePrice, ' . '
                        (CASE ' . '
                            WHEN a.priceStartingAt IS NOT NULL THEN a.priceStartingAt ' . '
                            WHEN b.priceStartingAt IS NOT NULL THEN b.priceStartingAt ' . '
                            WHEN c.priceStartingAt IS NOT NULL THEN c.priceStartingAt ' . '
                            WHEN d.priceStartingAt IS NOT NULL THEN d.priceStartingAt ' . '
                        ELSE NULL END) AS priceStartingAt, ' . '
                        (CASE ' . '
                            WHEN a.priceReplacer IS NOT NULL THEN a.priceReplacer ' . '
                            WHEN b.priceReplacer IS NOT NULL THEN b.priceReplacer ' . '
                            WHEN c.priceReplacer IS NOT NULL THEN c.priceReplacer ' . '
                            WHEN d.priceReplacer IS NOT NULL THEN d.priceReplacer ' . '
                        ELSE NULL END) AS priceReplacer, ' . '
                        (CASE ' . '
                            WHEN a.tax IS NOT NULL THEN a.tax ' . '
                            WHEN b.tax IS NOT NULL THEN b.tax ' . '
                            WHEN c.tax IS NOT NULL THEN c.tax ' . '
                            WHEN d.tax IS NOT NULL THEN d.tax ' . '
                        ELSE NULL END) AS taxNote, ' . '
                        (CASE ' . '
                            WHEN a.discount IS NOT NULL THEN a.discount ' . '
                            WHEN b.discount IS NOT NULL THEN b.discount ' . '
                            WHEN c.discount IS NOT NULL THEN c.discount ' . '
                            WHEN d.discount IS NOT NULL THEN d.discount ' . '
                        ELSE NULL END) AS discount, ' . '
                        (CASE ' . '
                            WHEN a.color IS NOT NULL THEN a.color ' . '
                            WHEN b.color IS NOT NULL THEN b.color ' . '
                            WHEN c.color IS NOT NULL THEN c.color ' . '
                            WHEN d.color IS NOT NULL THEN d.color ' . '
                        ELSE NULL END) AS color, ' . '
                        (CASE ' . '
                            WHEN a.size IS NOT NULL THEN a.size ' . '
                            WHEN b.size IS NOT NULL THEN b.size ' . '
                            WHEN c.size IS NOT NULL THEN c.size ' . '
                            WHEN d.size IS NOT NULL THEN d.size ' . '
                        ELSE NULL END) AS size  ' . '
                        FROM tl_gutesio_data_child_product a ' . '
                        JOIN tl_gutesio_data_child ca ON a.childId = ca.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cb ON ca.parentChildId = cb.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_product b ON b.childId = cb.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cc ON cb.parentChildId = cc.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_product c ON c.childId = cc.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cd ON cc.parentChildId = cd.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_product d ON d.childId = cd.uuid ' . '
                        WHERE a.childId = ?')
                        ->execute($row['uuid'])->fetchAssoc();
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
                        } elseif ((!$productData['price'])/* && !$productData['priceStartingAt']*/) {
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
                case 'event':
                    $eventData = $database->prepare('SELECT ' . '
                        (CASE ' . '
                            WHEN a.beginDate IS NOT NULL THEN a.beginDate ' . '
                            WHEN b.beginDate IS NOT NULL THEN b.beginDate ' . '
                            WHEN c.beginDate IS NOT NULL THEN c.beginDate ' . '
                            WHEN d.beginDate IS NOT NULL THEN d.beginDate ' . '
                        ELSE NULL END) AS beginDate, ' . '
                        (CASE ' . '
                            WHEN a.beginTime IS NOT NULL THEN a.beginTime ' . '
                            WHEN b.beginTime IS NOT NULL THEN b.beginTime ' . '
                            WHEN c.beginTime IS NOT NULL THEN c.beginTime ' . '
                            WHEN d.beginTime IS NOT NULL THEN d.beginTime ' . '
                        ELSE NULL END) AS beginTime, ' . '
                        (CASE ' . '
                            WHEN a.endDate IS NOT NULL THEN a.endDate ' . '
                            WHEN b.endDate IS NOT NULL THEN b.endDate ' . '
                            WHEN c.endDate IS NOT NULL THEN c.endDate ' . '
                            WHEN d.endDate IS NOT NULL THEN d.endDate ' . '
                        ELSE NULL END) AS endDate, ' . '
                        (CASE ' . '
                            WHEN a.endTime IS NOT NULL THEN a.endTime ' . '
                            WHEN b.endTime IS NOT NULL THEN b.endTime ' . '
                            WHEN c.endTime IS NOT NULL THEN c.endTime ' . '
                            WHEN d.endTime IS NOT NULL THEN d.endTime ' . '
                        ELSE NULL END) AS endTime, ' . '
                        (CASE ' . '
                            WHEN a.locationElementId IS NOT NULL THEN a.locationElementId ' . '
                            WHEN b.locationElementId IS NOT NULL THEN b.locationElementId ' . '
                            WHEN c.locationElementId IS NOT NULL THEN c.locationElementId ' . '
                            WHEN d.locationElementId IS NOT NULL THEN d.locationElementId ' . '
                        ELSE NULL END) AS locationElementId, ' . '
                        (CASE ' . '
                            WHEN a.recurring IS NOT NULL THEN a.recurring ' . '
                            WHEN b.recurring IS NOT NULL THEN b.recurring ' . '
                            WHEN c.recurring IS NOT NULL THEN c.recurring ' . '
                            WHEN d.recurring IS NOT NULL THEN d.recurring ' . '
                        ELSE NULL END) AS recurring, ' . '
                        (CASE ' . '
                            WHEN a.recurrences IS NOT NULL THEN a.recurrences ' . '
                            WHEN b.recurrences IS NOT NULL THEN b.recurrences ' . '
                            WHEN c.recurrences IS NOT NULL THEN c.recurrences ' . '
                            WHEN d.recurrences IS NOT NULL THEN d.recurrences ' . '
                        ELSE NULL END) AS recurrences, ' . '
                        (CASE ' . '
                            WHEN a.repeatEach IS NOT NULL THEN a.repeatEach ' . '
                            WHEN b.repeatEach IS NOT NULL THEN b.repeatEach ' . '
                            WHEN c.repeatEach IS NOT NULL THEN c.repeatEach ' . '
                            WHEN d.repeatEach IS NOT NULL THEN d.repeatEach ' . '
                        ELSE NULL END) AS repeatEach, ' . '
                        (CASE ' . '
                            WHEN a.appointmentUponAgreement IS NOT NULL THEN a.appointmentUponAgreement ' . '
                            WHEN b.appointmentUponAgreement IS NOT NULL THEN b.appointmentUponAgreement ' . '
                            WHEN c.appointmentUponAgreement IS NOT NULL THEN c.appointmentUponAgreement ' . '
                            WHEN d.appointmentUponAgreement IS NOT NULL THEN d.appointmentUponAgreement ' . '
                        ELSE NULL END) AS appointmentUponAgreement ' . '
                        FROM tl_gutesio_data_child_event a ' . '
                        JOIN tl_gutesio_data_child ca ON a.childId = ca.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cb ON ca.parentChildId = cb.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_event b ON b.childId = cb.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cc ON cb.parentChildId = cc.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_event c ON c.childId = cc.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cd ON cc.parentChildId = cd.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_event d ON d.childId = cd.uuid ' . '
                        WHERE a.childId = ?')
                        ->execute($row['uuid'])->fetchAssoc();

                    $beginDateTime = new \DateTime();
                    $beginDateTime->setTimestamp($eventData['beginDate']);
                    // Add one day so events are still shown on the day they expire
                    $beginDateTime->setDate(
                        $beginDateTime->format('Y'),
                        $beginDateTime->format('m'),
                        (int) $beginDateTime->format('d') + 1
                    );
                    $endDateTime = new \DateTime();
                    $endDateTime->setTimestamp($eventData['endDate']);

                    if ($beginDateTime->getTimestamp() < time()) {
                        if ($eventData['recurring']) {
                            $repeatEach = StringUtil::deserialize($eventData['repeatEach']);
                            $times = (int) $eventData['recurrences'];
                            $value = (int) $repeatEach['value'];
                            if ($times === 0) {
                                $times = 100;
                            }
                            while ($times > 0) {
                                $times -= 1;
                                switch ($repeatEach['unit']) {
                                    case 'weeks':
                                        $beginDateTime->setDate(
                                            $beginDateTime->format('Y'),
                                            $beginDateTime->format('m'),
                                            ((int) $beginDateTime->format('d')) + ($value * 7)
                                        );
                                        if ($times > 0) {
                                            $nextDateTime = clone $beginDateTime;
                                            $nextDateTime->setDate(
                                                $nextDateTime->format('Y'),
                                                $nextDateTime->format('m'),
                                                ((int) $nextDateTime->format('d')) + ($value * 7)
                                            );
                                        }
                                        $endDateTime = $beginDateTime;
                                        break;
                                    case 'months':
                                        $beginDateTime->setDate(
                                            $beginDateTime->format('Y'),
                                            ((int) $beginDateTime->format('m')) + $value,
                                            $beginDateTime->format('d')
                                        );
                                        if ($times > 0) {
                                            $nextDateTime = clone $beginDateTime;
                                            $nextDateTime->setDate(
                                                $nextDateTime->format('Y'),
                                                ((int) $nextDateTime->format('m')) + $value,
                                                $nextDateTime->format('d')
                                            );
                                        }
                                        $endDateTime = $beginDateTime;
                                        break;
                                    case 'years':
                                        $beginDateTime->setDate(
                                            ((int) $beginDateTime->format('Y')) + $value,
                                            $beginDateTime->format('m'),
                                            $beginDateTime->format('d')
                                        );
                                        if ($times > 0) {
                                            $nextDateTime = clone $beginDateTime;
                                            $nextDateTime->setDate(
                                                ((int) $nextDateTime->format('Y')) + $value,
                                                $nextDateTime->format('m'),
                                                $nextDateTime->format('d')
                                            );
                                        }
                                        $endDateTime = $beginDateTime;
                                        break;
                                    default:
                                        $beginDateTime->setDate(
                                            $beginDateTime->format('Y'),
                                            $beginDateTime->format('m'),
                                            ((int) $beginDateTime->format('d')) + $value
                                        );
                                        if ($times > 0) {
                                            $nextDateTime = clone $beginDateTime;
                                            $nextDateTime->setDate(
                                                $nextDateTime->format('Y'),
                                                $nextDateTime->format('m'),
                                                ((int) $nextDateTime->format('d')) + $value
                                            );
                                        }

                                        break;
                                }
                                if ($beginDateTime->getTimestamp() >= time()) {
                                    break;
                                } elseif ($times === 0 && ($endDateTime > 0) && $endDateTime->getTimestamp() < time()) {
                                    $tooOld = true;

                                    break;
                                }
                            }
                        } elseif (($endDateTime > 0) && $endDateTime->getTimestamp() < time()) {
                            $tooOld = true;
                        }
                    }

                    // remove the extra day added previously
                    $beginDateTime->setDate(
                        $beginDateTime->format('Y'),
                        $beginDateTime->format('m'),
                        (int) $beginDateTime->format('d') - 1
                    );
                    $eventData['beginDate'] = $beginDateTime->format('d.m.Y');
                    $eventData['endDate'] = $endDateTime->format('d.m.Y');
                    if ($nextDateTime) {
                        $eventData['nextDate'] = $nextDateTime->format('d.m.Y');
                    }
                    if ($eventData['beginTime'] !== null) {
                        $eventData['beginTime'] = gmdate('H:i', $eventData['beginTime']) . ' Uhr'; //ToDo
                    }
                    if ($eventData['endTime'] !== null) {
                        $eventData['endTime'] = gmdate('H:i', $eventData['endTime']);
                    }
                    if ($eventData['beginDate'] === '01.01.1970') {
                        $eventData['beginDate'] = '';
                    }
                    if ($eventData['endDate'] === '01.01.1970') {
                        $eventData['endDate'] = '';
                    }
                    if ($eventData['appointmentUponAgreement']) {
                        $fieldValue = $GLOBALS['TL_LANG']['tl_gutesio_data_child']['appointmentUponAgreementContent'];
                        if ($eventData['beginDate']) {
                            $fieldValue .= ' (';
                            if (!$eventData['endDate']) {
                                $fieldValue .= $GLOBALS['TL_LANG']['tl_gutesio_data_child']['appointmentUponAgreement_startingAt'] . ' ';
                            }
                            $fieldValue .= $eventData['beginDate'];
                            if ($eventData['beginTime']) {
                                $fieldValue .= ' ' . $eventData['beginTime'];
                            }
                            if ($eventData['endDate']) {
                                $fieldValue .= ' - ' . $eventData['endDate'];
                                if ($eventData['endTime']) {
                                    $fieldValue .= ' ' . $eventData['endTime'];
                                }
                            }
                            $fieldValue .= ')';
                        }
                        $eventData['beginDate'] = '';
                        $eventData['beginTime'] = '';
                        $eventData['endDate'] = '';
                        $eventData['endTime'] = '';
                        $eventData['appointmentUponAgreement'] = $fieldValue;
                        $tooOld = false;
                    } else {
                        $eventData['appointmentUponAgreement'] = '';
                    }

                    $elementModel = GutesioDataElementModel::findBy('uuid', $eventData['locationElementId']);
                    if ($elementModel !== null) {
                        $eventData['locationElementName'] = html_entity_decode($elementModel->name);
                    } else {
                        $elementId = $row['elementId'];
                        $elementModel = GutesioDataElementModel::findBy('uuid', $elementId);
                        $eventData['locationElementName'] = html_entity_decode($elementModel->name);
                    }

                    //hotfix special char
                    $eventData['locationElementName'] = str_replace('&#39;', "'", $eventData["locationElementName"]);

                    if (!empty($eventData)) {
                        $childRows[$key] = array_merge($row, $eventData);
                    }
                    if ($dateFilter) {
                        // date filter will be applied later on
                        $tooOld = false;
                    }

                    break;
                case 'job':
                    $jobData = $database->prepare('SELECT beginDate AS beginDate ' .
                        'FROM tl_gutesio_data_child_job ' .
                        'JOIN tl_gutesio_data_child ON tl_gutesio_data_child_job.childId = tl_gutesio_data_child.uuid ' .
                        'WHERE childId = ?')
                        ->execute($row['uuid'])->fetchAssoc();
                    if ((string) $jobData['beginDate'] === '') {
                        $jobData['beginDate'] = 'ab sofort';
                    } else {
                        $jobData['beginDate'] = date('d.m.Y', $jobData['beginDate']);
                    }
                    if (!empty($jobData)) {
                        $childRows[$key] = array_merge($row, $jobData);
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

            if (!$tooOld || $eventData['appointmentUponAgreement'] || !$checkEventTime) {
                $vendorUuid = $database->prepare(
                    'SELECT * FROM tl_gutesio_data_child_connection WHERE childId = ? LIMIT 1'
                )->execute($row['uuid'])->fetchAssoc();

                $vendor = $database->prepare(
                    'SELECT * FROM tl_gutesio_data_element WHERE uuid = ?'
                )->execute($vendorUuid['elementId'])->fetchAssoc();
                $childRows[$key]['elementName'] = $vendor['name'] ? html_entity_decode($vendor['name']) : '';

                //hotfix special char
                $childRows[$key]['elementName'] = str_replace('&#39;', "'", $childRows[$key]['elementName']);

                $objSettings = GutesioOperatorSettingsModel::findSettings();
                $url = Controller::replaceInsertTags('{{link_url::' . $objSettings->showcaseDetailPage . '}}');

                if (C4GUtils::endsWith($url, '.html')) {
                    $href = str_replace('.html', '/' . strtolower(str_replace(['{', '}'], '', $vendor['alias'])) . '.html', $url);
                } else {
                    $href = $url . '/' . strtolower(str_replace(['{', '}'], '', $vendor['alias']));
                }

                $childRows[$key]['elementLink'] = $href ?: '';
            } else {
                unset($childRows[$key]);
            }
        }

        return array_values($childRows);
    }

    public function getElementData($childId)
    {
        $service = ShowcaseService::getInstance();
        $result = $service->loadByChildId($childId);

        foreach ($result as $key => $row) {
            $result[$key]['types'] = implode(', ', array_column($row['types'], 'label'));
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
                    $beginTstamp = strtotime($datum['beginDate']);
                    $endTstamp = strtotime($datum['endDate']);
                    if ($filterFrom !== 0) {
                        $fromDt = (new \DateTime())->setTimestamp($filterFrom);
                        $filterFrom = $fromDt->setTime(0, 0, 0)->getTimestamp();
                    }
                    if ($filterUntil !== 0) {
                        $untilDt = (new \DateTime())->setTimestamp($filterUntil + 86400);
                        $filterUntil = $untilDt->setTime(23, 59, 59)->getTimestamp();
                    }
                    $beginDateMatchesFilter = ($filterFrom === 0 || ($beginTstamp >= $filterFrom))
                        && ($filterUntil === 0 || ($beginTstamp <= $filterUntil));
                    $endDateMatchesFilter = !$endTstamp || ($filterUntil === 0) || ($endTstamp <= $filterUntil);
                    if ($beginDateMatchesFilter && $endDateMatchesFilter) {
                        $result[] = $datum;
                    }
                }
            }
        }

        usort($result, function ($a, $b) {
            $aTstamp = strtotime($a['beginDate']);
            $bTstamp = strtotime($b['beginDate']);
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
