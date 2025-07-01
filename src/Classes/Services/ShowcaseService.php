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
use con4gis\MapsBundle\Resources\contao\models\C4gMapProfilesModel;
use con4gis\MapsBundle\Classes\Services\AreaService;
use Contao\Database;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use gutesio\DataModelBundle\Classes\ShowcaseResultConverter;
use gutesio\DataModelBundle\Classes\TagDetailFieldGenerator;
use gutesio\DataModelBundle\Classes\TagFieldUtil;
use gutesio\DataModelBundle\Classes\TypeFieldUtil;
use gutesio\OperatorBundle\Classes\Cache\ShowcaseListApiCache;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use Org\Heigl\Hyphenator\Hyphenator;
use Org\Heigl\Hyphenator\Options;

class ShowcaseService
{
    private static $instance = null;

    const INTERNAL = 'internal';
    const EXTERNAL = 'external';
    const INTER_REGIONAL = 'interregional';

    /**
     * @var ShowcaseListApiCache
     */
    private $cache = null;

    /**
     * @var ShowcaseResultConverter
     */
    private $converter = null;

    /**
     * @var VisitCounterService
     */
    private $visitCounter = null;



    private $filterConnector = 'AND';


    //ToDo AND search to find exact results
    //ToDo search type values
    const FILTER_FIELDS = ['name_'=>1000,'alias_'=>1000,'name'=>60,'alias'=>60,'description'=>30,'contactName'=>40,'contactStreet'=>1,'contactStreetNumber'=>1,'contactCity'=>1,'contactZip'=>1,'locationStreet'=>30,'locationStreetNumber'=>1,'locationCity'=>30,'locationZip'=>60, 'extendedSearchTerms'=>60];

    public static function getFilterSQLString() {
        if(C4GUtils::isBackend()) {
            return '';
        }

        $result = '(';
        //1. entry, 2. part left, 3. part right
        foreach (ShowcaseService::FILTER_FIELDS as $key=>$weight) {
            if ($weight == 1000) {
                $key = str_replace('_','',$key);
                If ($result == '(') {
                    $result .= 'UPPER(`'.$key.'`) = ? OR UPPER(`'.$key.'`) = ? OR UPPER(`'.$key.'`) = ?';
                } else {
                    $result .= ' OR UPPER(`'.$key.'`) = ? OR UPPER(`'.$key.'`) = ? OR UPPER(`'.$key.'`) = ?';
                }
            } else {
                If ($result == '(') {
                    $result .= 'UPPER(`'.$key.'`) LIKE ? OR UPPER(`'.$key.'`) LIKE ? OR UPPER(`'.$key.'`) LIKE ?';
                } else {
                    $result .= ' OR UPPER(`'.$key.'`) LIKE ? OR UPPER(`'.$key.'`) LIKE ? OR UPPER(`'.$key.'`) LIKE ?';
                }
            }
        }
        $result .= ')';
        return $result;
    }

//    public static function getFilterSQLStringRelevance($result) {
//
//        $database = Database::getInstance();
//
//        $first = true;
//        $result .= '(';
//        //$key = '';
//        foreach (ShowcaseService::FILTER_FIELDS as $key=>$weight) {
//            If ($first) {
//          //      $keys = $key;
//                $result .= $weight.' * (MATCH (UPPER(`'.$key.'`)) AGAINST (? IN BOOLEAN MODE))';
//                $first = false;
//            } else {
//            //    $keys .= ', '.$key;
//                $result .= ' + '.$weight.' * (MATCH (UPPER(`'.$key.'`)) AGAINST (? IN BOOLEAN MODE))';
//            }
//        }
//        $result .= ') AS relevance';
//
//        //$database->prepare('ALTER TABLE tl_gutesio_data_element ADD FULLTEXT('.$keys.')')->execute();
//
//
//        return $result;
//    }

    public static function getFilterSQLStringWeight() {
        if(C4GUtils::isBackend()) {
            return '';
        }

        $result = '';
        //1. entry, 2. part left, 3. part right
        foreach (ShowcaseService::FILTER_FIELDS as $key=>$weight) {
            if ($weight == 1000) {
                $key = str_replace('_','',$key);
                If ($result == '') {
                    $result .= 'IF (UPPER(`'.$key.'`) = ? OR UPPER(`'.$key.'`) = ? OR UPPER(`'.$key.'`) = ?, '.$weight.', 0)';
                } else {
                    $result .= ' + IF (UPPER(`'.$key.'`) = ? OR UPPER(`'.$key.'`) = ? OR UPPER(`'.$key.'`) = ?, '.$weight.', 0)';
                }
            } else {
                If ($result == '') {
                    $result .= 'IF (UPPER(`'.$key.'`) LIKE ? OR UPPER(`'.$key.'`) LIKE ? OR UPPER(`'.$key.'`) LIKE ?, '.$weight.', 0)';
                } else {
                    $result .= ' + IF (UPPER(`'.$key.'`) LIKE ? OR UPPER(`'.$key.'`) LIKE ? OR UPPER(`'.$key.'`) LIKE ?, '.$weight.', 0)';
                }
            }
        }
        $result .= ' AS weight';

        return $result; //self::getFilterSQLStringRelevance($result);
    }

    public static function getFilterSQLValueSet($filterString, $withoutWeight = false) {
        $newFilterString = trim(str_replace('%',' ', $filterString));
        $strParts = explode(',', $newFilterString);
        if (count($strParts) <= 1) {
            $strParts = explode(';', $newFilterString);
        }
        if (count($strParts) <= 1) {
            $strParts = explode('#', $newFilterString);
        }
        if (count($strParts) <= 1) {
            $strParts = explode('+', $newFilterString);
        }
        if (count($strParts) <= 1) {
            $strParts = explode('-', $newFilterString);
        }
        if (count($strParts) <= 1 && (strpos($newFilterString, ' ') !== false)) {
            $strParts = explode(' ', $newFilterString);
        }
        if (count($strParts) <= 1 && (strpos($newFilterString, ' ') === false)) {
            $hyphenatorOptions = new Options();
            $hyphenatorOptions->setHyphen('-')
                ->setDefaultLocale('de_DE')
                ->setRightMin(4)
                ->setLeftMin(4)
                ->setWordMin(8)
                ->setFilters('Simple')
                ->setTokenizers(['Whitespace', 'Punctuation']);
            $hyphenator = new Hyphenator();
            $hyphenator->setOptions($hyphenatorOptions);
            $hyphenatorResults = $hyphenator->hyphenate($newFilterString);
            if ($hyphenatorResults && count($hyphenatorResults) > 0) {
                foreach($hyphenatorResults as $result) {
                    $strParts = explode('-',$result);
                    break; //Todo more parts and additional "whole string" without hyphen parts
                }
            }
        }

        $partArr = [];
        foreach($strParts as $part) {
            $part = trim($part);
            $part = str_replace([",",";","+","-","#"], "", $part);
            if ($pos = strpos($part,'*IN')) {
                $part = substr($part,0,$pos+1);
            }
            if ($part) {
                $partArr[] = $part;
            }
        }

        $filterString = strtoupper($newFilterString);
        $extraFilterString1 = $partArr[0];
        $extraFilterString2 = count($partArr) > 1 ? $partArr[1] : $partArr[0];

        $likeArr = [];
        $weightArr = [];
        $relevanceArr = [];
        foreach (ShowcaseService::FILTER_FIELDS as $key=>$weight) {
            if ($weight == 1000) {
                $likeArr[] = $filterString;
                $likeArr[] = $extraFilterString1;
                $likeArr[] = $extraFilterString2;

                if (!$withoutWeight) {
                    $weightArr[] = $filterString;
                    $weightArr[] = $extraFilterString1;
                    $weightArr[] = $extraFilterString2;
                }
            } else {
                $likeArr[] = '%'.$filterString.'%';
                $likeArr[] = '%'.$extraFilterString1.'%';
                $likeArr[] = '%'.$extraFilterString2.'%';

                if (!$withoutWeight) {
                    $weightArr[] = '%'.$filterString.'%';
                    $weightArr[] = '%'.$extraFilterString1.'%';
                    $weightArr[] = '%'.$extraFilterString2.'%';
                }
            }

            //$relevanceArr[] = '+'.$filterString.', +'.$extraFilterString;
        }


        $resultArr = array_merge($likeArr, $weightArr, $relevanceArr);
        //C4gLogModel::addLogEntry('operator', "Result: ".implode(',',$resultArr));

        return $resultArr;
    }

    /**
     * @return null
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        // TODO use kernel.cache_dir
        $this->cache = ShowcaseListApiCache::getInstance('../var/cache/prod/con4gis');
        $this->converter = new ShowcaseResultConverter();
        $this->visitCounter = new VisitCounterService();
    }

    /**
     * Generates a random key used for identifying the client request.
     */
    public function createRandomKey()
    {
        return sha1(random_int(0, 999999));
    }

    public function loadRelatedShowcases($arrShowcase) : array
    {
        $arrShowcase['showcaseIds'] = $arrShowcase['showcaseIds'] && is_array($arrShowcase['showcaseIds']) ? $arrShowcase['showcaseIds'] : [];
        $showcaseIds = array_column($arrShowcase['showcaseIds'], 'value');
        if (count($showcaseIds) > 0) {
            $idString = '(';
            foreach ($showcaseIds as $key => $showcaseId) {
                $idString .= '"' . $showcaseId . '"';
                if (!(array_key_last($showcaseIds) === $key)) {
                    $idString .= ',';
                }
            }
            $idString .= ')';
            if (!($idString === '()')) {
                $showcases = Database::getInstance()
                    ->execute(
                        'SELECT * FROM tl_gutesio_data_element ' .
                        "WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') AND `uuid` IN $idString"
                    )->fetchAllAssoc();
                $relatedShowcases = $showcases;
                $returnData = [];
                // check if the relation is bidirectional
                foreach ($relatedShowcases as $relatedShowcase) {
                    //$relatedUuids = StringUtil::deserialize($relatedShowcase['showcaseIds']);
                    //if ($arrShowcase && is_array($arrShowcase) && $relatedUuids && is_array($relatedUuids) && in_array($arrShowcase['uuid'], $relatedUuids)) {
                    $returnData[] = $relatedShowcase;
                    //}
                }
                $returnData = $this->converter->convertDbResult($returnData, ['loadTagsComplete' => true]);
                if (count($returnData) > 1 && !($returnData[0])) {
                    $returnData = [$returnData];
                }
            } else {
                $returnData = [];
            }

            return $returnData;
        }

        return [];
    }

    public function loadByChildId($childId) : array
    {
        $database = Database::getInstance();
        $elementIds = $database->prepare(
            'SELECT DISTINCT elementId FROM tl_gutesio_data_child_connection WHERE childId = ?'
        )->execute($childId)->fetchAllAssoc();
        $elementIds = array_column($elementIds, 'elementId');

        if (count($elementIds) > 0) {
            $idString = '(';
            foreach ($elementIds as $key => $showcaseId) {
                $idString .= '"' . $showcaseId . '"';
                if (!(array_key_last($elementIds) === $key)) {
                    $idString .= ',';
                }
            }
            $idString .= ')';
            if (!($idString === '()')) {
                $showcases = $database
                    ->execute(
                        'SELECT * FROM tl_gutesio_data_element ' .
                        "WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') AND `uuid` IN $idString"
                    )->fetchAllAssoc();
                $returnData = $this->converter->convertDbResult($showcases, ['loadTagsComplete' => true]);
                if (count($elementIds) === 1) {
                    $returnData = [$returnData];
                }
            } else {
                $returnData = [];
            }

            return $returnData;
        }

        return [];
    }

    public function loadDataChunk(
        $params,
        $offset,
        $limit = 30,
        $typeIds = [],
        $tagIds = [],
        $elementIds = [],
        $restrictedPostals = [],
        ModuleModel $moduleModel = null
    ) {
        $dbParams = [];
        $sorting = 'random';
        if ($params && is_array($params)) {
            $searchString = key_exists('filter', $params) ? $params['filter'] : '';
            $sorting = key_exists('sorting',$params) ? $params['sorting'] : 'random';
            $randKey =  key_exists('randKey',$params) ? $params['randKey'] : '';
            $position = key_exists('pos',$params) && str_contains($params['pos'], ",") ? explode(',', $params['pos']) : '';
        }
        if (!$randKey) {
            $randKey = $this->getSeedForLoading();
        }
        $seed = $randKey;
        $execQuery = true;
        if ($sorting) {
            switch ($sorting) {
                case 'random':
                    // gets sorted via SQL
                    $execQuery = true;

                    break;
                case 'distance':
                    $arrIdsWithDistances = $this->generateDistanceSortingMap($position, $searchString, $typeIds, $tagIds, $elementIds, $restrictedPostals);
                    $this->writeIntoCache($this->getCacheKey($randKey, $searchString, $sorting, $tagIds, $typeIds), $arrIdsWithDistances);
                    $arrIdsWithDistances = array_slice($arrIdsWithDistances, $offset, $limit);
                    if (count($arrIdsWithDistances) > 0) {
                        $arrResult = $this->loadByIds($arrIdsWithDistances, true);
                    } else {
                        $arrResult = [];
                    }
                    $execQuery = false;

                    break;
                default:
                    $execQuery = true;

                    break;
            }
        }

        if ($execQuery) {
            $params = [];
            if ($searchString) {
                $sql = 'SELECT DISTINCT e.*, ' . self::getFilterSQLStringWeight() . " FROM tl_gutesio_data_element AS e ";

                $additionalOrderBy = ' weight DESC ';
                $searchString = $this->updateSearchStringForNonExactSearch($searchString);
                $params = self::getFilterSQLValueSet($searchString);
            } else {

                $sql = "SELECT DISTINCT e.* FROM tl_gutesio_data_element AS e";

            }

            $blockedTypeIds = [];
            if ($moduleModel->gutesio_data_mode === "4") {
                $blockedTypeIds = StringUtil::deserialize($moduleModel->gutesio_data_blocked_types);
            }

            if ($typeIds || $blockedTypeIds) {
                $sql .= " JOIN tl_gutesio_data_element_type ON e.uuid = tl_gutesio_data_element_type.elementId ";
            }

            if ($tagIds) {
                $sql .= " JOIN tl_gutesio_data_tag_element ON e.uuid = tl_gutesio_data_tag_element.elementId ";
            }

            $sql .= " WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') ";

            if ($searchString) {
                $sql .= 'AND ' . self::getFilterSQLString();

                if (!empty($restrictedPostals)) {
                    $sql .= ' AND locationZip ' . C4GUtils::buildInString($restrictedPostals);
                    $params = array_merge($params, $restrictedPostals);
                }
            }

            if ($typeIds || $blockedTypeIds) {
                if ($typeIds) {
                    $sql .= " AND tl_gutesio_data_element_type.typeId " . C4GUtils::buildInString($typeIds);
                    $params = array_merge($params, $typeIds);
                }
                if ($blockedTypeIds) {
                    $sql .= " AND tl_gutesio_data_element_type.typeId NOT " . C4GUtils::buildInString($blockedTypeIds);
                    $params = array_merge($params, $blockedTypeIds);
                }
            }

            if ($tagIds) {
                $sql .= " AND tl_gutesio_data_tag_element.tagId " . C4GUtils::buildInString($tagIds);
                $params = array_merge($params, $tagIds);
            }

            if ($elementIds) {
                $sql .= " AND e.uuid " . C4GUtils::buildInString($elementIds);
                $params = array_merge($params, $elementIds);
            }

            if (!empty($restrictedPostals)) {
                $params = array_merge($params, $restrictedPostals);
                $sql .= ' AND locationZip ' . C4GUtils::buildInString($restrictedPostals);
            }

            $sortClause = "";
            if ($sorting) {
                $arrSort = explode('_', $sorting);
                if ($searchString && ($sorting == 'random')) {
                    $sortClause = sprintf(" ORDER BY weight DESC LIMIT %s, %s", $offset, $limit);
                } elseif ($sorting == 'random') {
                    $sortClause = sprintf(' ORDER BY RAND(%s) LIMIT %s, %s', $seed, $offset, $limit);
                } elseif ($arrSort && $arrSort[0] && $arrSort[1]) {
                    $sortClause = ' ORDER BY ' . $arrSort[0]. ' ' . $arrSort[1];
                    if (!empty($additionalOrderBy)) {
                        $sortClause .= "," . $additionalOrderBy;
                    }

                    $limitClause = sprintf(' LIMIT %s, %s', $offset, $limit);
                    $sortClause .= $limitClause;
                }
            }

            $sql .= $sortClause;
            $stm = Database::getInstance()->prepare($sql);

            $arrResult = $stm->execute(...$params)->fetchAllAssoc();
        }

        return $this->convertDbResult($arrResult, ['loadTagsComplete' => true]);
    }

    private function formatDistance($distanceInMeters)
    {
        if (!$distanceInMeters) {
            return $distanceInMeters;
        }
        $unit = ' m';
        if ($distanceInMeters >= 1000) {
            $distanceInMeters = round($distanceInMeters / 1000, 3);
            $unit = ' km';
        } else {
            $distanceInMeters = number_format($distanceInMeters, 2, ",", ".");
        }

        return $distanceInMeters . $unit;
    }

    private function updateSearchStringForNonExactSearch($searchString)
    {
        $arrTerms = explode(' ', $searchString);
        $result = '%';
        foreach ($arrTerms as $term) {
            $term = strtoupper($term);
            /* straße, str., strasse */
            $term = str_replace('STR:','STR', $term);
            $term = str_replace('STRASSE','STR', $term);
            $term = str_replace('STRA?E','STR', $term);
            $result .= $term . '%';
        }

        return $result;
    }

    /**
     * Used in cases where we have a cache hit. The IDs are coming from cache and will be searched
     * for and returned in THE SAME order as they came in.
     * @param $arrIds
     * @return array
     */
    private function loadByIds($arrIds, $withDistance = false) : array
    {
        if (is_array($arrIds[0])) {
            // sorting by distance
            $idString = '';
            foreach ($arrIds as $key => $arrId) {
                if (!empty($arrId['id'])) {
                    $idString .= $arrId['id'];
                    if ($key !== array_key_last($arrIds)) {
                        $idString .= ',';
                    }
                }
            }
        } else {
            $idString = implode(',', $arrIds);
        }
        $sql = "SELECT * FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') AND id IN ($idString)";
        $arrResult = Database::getInstance()
            ->prepare($sql)
            ->execute()
            ->fetchAllAssoc();
        // the database ignores the order of the IDs.
        // so we have to sort the data here again
        $sortedResult = [];
        foreach ($arrIds as $id) {
            if (is_array($id)) {
                $id = $id['id'];
            }
            foreach ($arrResult as $result) {
                if ($result['id'] === $id) {
                    $sortedResult[] = $result;

                    break;
                }
            }
        }
        $arrResult = $sortedResult;
        if ($withDistance) {
            // add distance to data
            foreach ($arrResult as $key => $item) {
                $arrResult[$key]['distance'] = $this->formatDistance($arrIds[$key]['distance']);
            }
        }

        return $arrResult;
    }

    private function checkTypes($elementId, $typeIds) {
        $idString = '(';
        foreach ($typeIds as $key => $id) {
            $idString .= "\"$id\"";
            if (array_key_last($typeIds) !== $key) {
                $idString .= ',';
            }
        }
        $idString .= ')';
        $count = Database::getInstance()->prepare('SELECT COUNT(1) AS rowCount FROM tl_gutesio_data_element_type ' .
            'WHERE elementId = ? AND typeId IN '.$idString)->execute($elementId)->rowCount;

        return ($count && $count > 0);
    }

    public function loadByAlias($alias, $typeIds = []) : array
    {
        $arrResult = Database::getInstance()
            ->prepare('SELECT * FROM tl_gutesio_data_element ' .
                "WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') AND alias = ?")
            ->execute($alias)->fetchAllAssoc();

        if (!$arrResult || (count($typeIds) && !$this->checkTypes($arrResult['uuid'], $typeIds))) {
            return [];
        }

        $returnData = $this->convertDbResult($arrResult, ['loadTagsComplete' => true, 'details' => true]);
        $tags = $returnData['tags'];
        foreach ($tags as $key => $tag) {
            $technicalKey = $tag['technicalKey'];
            $fields = TagDetailFieldGenerator::getFieldsForTag($technicalKey);
            $fieldConfs = [];
            foreach ($fields as $field) {
                $fieldConfs[] = $field->getConfiguration();
            }
            $tags[$key]['fields'] = $fieldConfs;
        }
        if ($tags !== null) {
            $returnData['tags'] = $tags;
        }

        // set name as contactName, if contactName is not set
        if ($returnData['contactable'] && !$returnData['contactName']) {
            $returnData['contactName'] = $returnData['name'];
        }

        $this->visitCounter->countShowcaseVisit($returnData['uuid'], $returnData['ownerMemberId']);

        return $returnData;
    }

    public function loadByUuid(string $uuid, bool $withExternal = false)
    {
        if ($withExternal) {
            $arrResult = Database::getInstance()
                ->prepare('SELECT * FROM tl_gutesio_data_element ' .
                    "WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '" . self::EXTERNAL . "' OR releaseType = '') AND uuid = ? LIMIT 1")
                ->execute($uuid)->fetchAllAssoc();
        } else {
            $arrResult = Database::getInstance()
                ->prepare('SELECT * FROM tl_gutesio_data_element ' .
                    "WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') AND uuid = ? LIMIT 1")
                ->execute($uuid)->fetchAllAssoc();
        }

        $returnData = $this->convertDbResult($arrResult, ['loadTagsComplete' => true, 'details' => true]);
        $typeString = '';
        foreach ($returnData['types'] as $key => $type) {
            $typeString .= $type['label'];
            if (array_key_last($returnData['types']) !== $key) {
                $typeString .= ',';
            }
        }
        $returnData['types'] = $typeString;

        return $returnData;
    }

    /**
     * Loads all showcase IDs and maps them to a random number used for sorting.
     */
    private function generateRandomSortingMap(
        $searchString,
        $typeIds = [],
        $tagIds = [],
        $elementIds = [],
        $restrictedPostals = []
    ) : array {
        $arrIds = $this->loadByTypes($typeIds, $searchString, $tagIds, $elementIds, $restrictedPostals);
        if (!$searchString) {
            shuffle($arrIds);
        }

        return $arrIds;
    }

    private function loadByTypes($typeIds, $searchString = '', $tagIds = [], $elementIds = [], $restrictedPostals = [])
    {
        $db = Database::getInstance();

        $elementIdString = $this->createIdStringForElements($typeIds, $searchString, $tagIds, $elementIds);

        if ($searchString !== '' || $elementIdString === '()') {
            // avoid executing broken query when no search string is given
            if ($searchString === '') {
                return [];
            }
            $sql = 'SELECT `id`, ' . self::getFilterSQLStringWeight() . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
        } else {
            $sql = 'SELECT `id` FROM tl_gutesio_data_element ' .
                "WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') AND `uuid` IN " . $elementIdString;
        }
        if ($searchString) {
            $this->filterConnector = 'AND'; // todo temporary fix for filter
            $sql .= ' ' . $this->filterConnector;
            $searchString = $this->updateSearchStringForNonExactSearch($searchString);
            // connect with OR here since the other condition is already considering the filter
            $whereClause = self::getFilterSQLString();
            $sql .= ' ' . $whereClause;
            if (!empty($restrictedPostals)) {
                $sql .= ' AND locationZip ' . C4GUtils::buildInString($restrictedPostals);
                //$sql .= ' ORDER BY weight DESC';
                $sql .= ' HAVING weight > 2.5 ORDER BY weight DESC';
                $arrResult = $db->prepare($sql)
                    ->execute(...self::getFilterSQLValueSet($searchString), ...$restrictedPostals)->fetchAllAssoc();
                // search for type name matches
                $typeResult = $db->prepare('SELECT `tl_gutesio_data_element`.`id` FROM `tl_gutesio_data_element` ' .
                    'JOIN `tl_gutesio_data_element_type` ON `tl_gutesio_data_element_type`.`elementId` = `tl_gutesio_data_element`.`uuid` ' .
                    'JOIN `tl_gutesio_data_type` ON `tl_gutesio_data_element_type`.`typeId` = `tl_gutesio_data_type`.`uuid` ' .
                    'WHERE UPPER(`tl_gutesio_data_type`.`name`) LIKE ? OR UPPER(`tl_gutesio_data_type`.`extendedSearchTerms`) LIKE ? AND locationZip ' . C4GUtils::buildInString($restrictedPostals)
                )->execute($searchString, $searchString, ...$restrictedPostals)->fetchAllAssoc();
            } else {
                //$searchString = strtoupper($searchString);
                //$sql .= ' ORDER BY weight DESC';
                $sql .= ' HAVING weight > 2.5 ORDER BY weight DESC';
                $values = self::getFilterSQLValueSet($searchString);
                $arrResult = $db->prepare($sql)
                    ->execute(...$values)->fetchAllAssoc();
                // search for type name matches
                $typeResult = $db->prepare('SELECT `tl_gutesio_data_element`.`id` FROM `tl_gutesio_data_element` ' .
                    'JOIN `tl_gutesio_data_element_type` ON `tl_gutesio_data_element_type`.`elementId` = `tl_gutesio_data_element`.`uuid` ' .
                    'JOIN `tl_gutesio_data_type` ON `tl_gutesio_data_element_type`.`typeId` = `tl_gutesio_data_type`.`uuid` ' .
                    'WHERE UPPER(`tl_gutesio_data_type`.`name`) LIKE ? OR UPPER(`tl_gutesio_data_type`.`extendedSearchTerms`) LIKE ?'
                )->execute($searchString, $searchString)->fetchAllAssoc();
            }

            $arrResult = array_merge($arrResult, $typeResult);
        } else {
            if (!empty($restrictedPostals)) {
                $sql .= ' AND locationZip ' . C4GUtils::buildInString($restrictedPostals);
                $arrResult = $db->prepare($sql)->execute(...$restrictedPostals)->fetchAllAssoc();
            } else {
                $arrResult = $db->execute($sql)->fetchAllAssoc();
            }
        }
        $returnIds = [];
        foreach ($arrResult as $result) {
            if (!in_array($result['id'], $returnIds)) {
                $returnIds[$result['id']] = $result['id'];
            }
        }

        return $returnIds;
    }

    private function createIdStringForElements(
        $typeIds,
        $searchString,
        $tagIds = [],
        $elementIds = [],
        $mode = 0
    ) {
        $db = Database::getInstance();
        $idString = '(';
        foreach ($typeIds as $key => $id) {
            $idString .= "\"$id\"";
            if (array_key_last($typeIds) !== $key) {
                $idString .= ',';
            }
        }
        $idString .= ')';

        $sql = 'SELECT DISTINCT `elementId` FROM tl_gutesio_data_element_type t JOIN tl_gutesio_data_element e ON t.elementId = e.uuid';
        if ($idString !== '()') {
            $sql .= ' WHERE t.`typeId` IN ' . $idString;
            if ($searchString !== '') {
                //$searchString = $this->updateSearchStringForNonExactSearch($searchString);
                $searchString = strtoupper($searchString);
                $sql .= ' AND UPPER(e.`name`) LIKE ?';
            }
        }

        // get element ids connected to valid types (type name is already checked here)
        if ($idString === '()' && $searchString !== '') {
            // no id constraint, but search constraint -> do not load everything
//            $arrElements = [];
            $searchString = $this->updateSearchStringForNonExactSearch($searchString);
            $sql .= ' AND UPPER(e.`name`) LIKE ?';
            $arrElements = $db->prepare($sql)->execute($searchString)->fetchAllAssoc();
        } elseif ($idString !== '()' && $searchString !== '') {
            // id constraint & search constraint
            $searchString = $this->updateSearchStringForNonExactSearch($searchString);
            $arrElements = $db->prepare($sql)->execute($searchString)->fetchAllAssoc();
        } else {
            if (count($elementIds)) {
                $arrElements = [];
                foreach ($elementIds as $elementId) {
                    $arrElements[] = ['elementId' => $elementId];
                }
            } else {
                $arrElements = $db->prepare($sql)->execute()->fetchAllAssoc();
            }
        }
        // filter tags by name
        $arrTags = $tagIds;
        $tagIdString = '(';
        foreach ($arrTags as $key => $tag) {
            $tagIdString .= '"' . $tag . '"';
            if (array_key_last($arrTags) !== $key) {
                $tagIdString .= ',';
            }
        }
        $tagIdString .= ')';
        $sql = 'SELECT DISTINCT `elementId`, COUNT(`elementId`) AS counter FROM tl_gutesio_data_tag_element';
        if ($tagIdString !== '()') {
            $sql .= ' WHERE `tagId` IN ' . $tagIdString . ' GROUP BY `elementId`';
        } else {
            $sql .= ' GROUP BY `elementId`';
        }

        if ($tagIdString === '()' && $searchString) {
            // no tagId constraint but search string, do not load everything
            $arrTaggedElements = [];
        } else {
            $arrTaggedElements = $db->prepare($sql)->execute()->fetchAllAssoc();
        }


        if ($mode === 3) {

            $ids = [];
            foreach ($arrTaggedElements as $taggedElement) {
                $ids[] = $taggedElement['elementId'];
            }

            $idString = implode("\",\"", $ids);

            return sprintf("(\"%s\")", $idString);
        }

        if ($searchString) {
            $additionalIds = $this->getIdsForTagAndTypeValues($searchString);
            $this->filterConnector = 'OR';
        } else {
            $additionalIds = [];
        }

        $arrElements = array_merge($arrElements, $arrTaggedElements, $additionalIds);
        $arrCompareElements = [];
        foreach ($arrElements as $arrElement) {
            $arrCompareElements[$arrElement['elementId']] = $arrElement;
        }

        $elementIdString = '(';

        foreach ($arrCompareElements as $key => $element) {
            $elementIdString .= '"' . $key . '"';
            if (array_key_last($arrCompareElements) !== $key) {
                $elementIdString .= ',';
            }
        }

        if (C4GUtils::endsWith($elementIdString, ',')) {
            $elementIdString = substr($elementIdString, 0, strlen($elementIdString) - 1);
        }

        $elementIdString .= ')';

        return $elementIdString;
    }

    private function getIdsForTagAndTypeValues($searchString)
    {
        // filter tag and type fields
        $typeFieldNames = TypeFieldUtil::getTypeFieldnames();
        $tagFieldNames = TagFieldUtil::getTagFieldnames();
        $sql = 'SELECT `elementId` FROM tl_gutesio_data_tag_element_values';
        $ctr = 0;

        $addWhere = true;
        foreach ($tagFieldNames as $key => $tagFieldName) {
            if ($addWhere) {
                $sql .= ' WHERE ';
                $addWhere = false;
            }
            $sql .= "(`tagFieldKey` = '".$tagFieldName."' AND UPPER(`tagFieldValue`) LIKE ?)";
            if (array_key_last($tagFieldNames) !== $key) {
                $sql .= ' OR ';
            }
            $ctr++;
        }
        $parameters = [];
        for ($i = 0; $i < $ctr; $i++) {
            $parameters[] = $searchString;
        }

        $arrTagIds = [];
        if (count($parameters) > 0) {
            $arrTagIds = Database::getInstance()->prepare($sql)->execute(...$parameters)->fetchAllAssoc();
            if (is_array($arrTagIds) && count($arrTagIds)) {
                $arrTagIds = array_values($arrTagIds);
            }
        }
        $sql = 'SELECT `elementId` FROM tl_gutesio_data_type_element_values';
        $ctr = 0;

        $addWhere = true;
        foreach ($typeFieldNames as $key => $typeFieldName) {
            if ($addWhere) {
                $sql .= ' WHERE ';
                $addWhere = false;
            }

            $sql .= "(`typeFieldKey` = '$typeFieldName' AND `typeFieldValue` LIKE ?)";
            if (array_key_last($typeFieldNames) !== $key) {
                $sql .= ' OR ';
            }
            $ctr++;
        }
        $parameters = [];
        for ($i = 0; $i < $ctr; $i++) {
            $parameters[] = $searchString;
        }

        $arrTypeIds = [];
        if (count($parameters) > 0) {
            $arrTypeIds = Database::getInstance()->prepare($sql)->execute(...$parameters)->fetchAllAssoc();
            if (is_array($arrTypeIds) && count($arrTypeIds)) {
                $arrTypeIds = array_values($arrTypeIds);
            }
        }

        return array_unique(array_merge($arrTagIds, $arrTypeIds));
    }

    private function generateDistanceSortingMap($userLocation, $searchString, $typeIds = [], $tagIds = [], $elementIds = [], $restrictedPostals = [])
    {
        $elementIdString = $this->createIdStringForElements($typeIds, $searchString, $tagIds, $elementIds);

        if ($elementIdString !== '()') {
            if ($searchString) {
                $sql = 'SELECT id, geox, geoy,releaseType, ' . self::getFilterSQLStringWeight() . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
                $sql .= ' AND `uuid` IN ' . $elementIdString;

                if (!empty($restrictedPostals)) {
                    $sql .= ' AND locationZip ' . C4GUtils::buildInString($restrictedPostals);
                }

//                $searchString = "%$searchString%";
                $searchString = $this->updateSearchStringForNonExactSearch($searchString);
                $whereClause = 'AND (' . self::getFilterSQLString() . ')';
                $sql .= ' ' . $whereClause;

                if (!empty($restrictedPostals)) {
                    $arrResult = Database::getInstance()->prepare($sql)
                        ->execute(self::getFilterSQLValueSet($searchString), ...$restrictedPostals)
                        ->fetchAllAssoc();
                } else {
                    $arrResult = Database::getInstance()->prepare($sql)
                        ->execute(self::getFilterSQLValueSet($searchString))
                        ->fetchAllAssoc();
                }
            } else {
                $sql = "SELECT id, geox, geoy, releaseType FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
                $sql .= ' AND `uuid` IN ' . $elementIdString;
                if (!empty($restrictedPostals)) {
                    $sql .= ' AND locationZip ' . C4GUtils::buildInString($restrictedPostals);
                }

                if (!empty($restrictedPostals)) {
                    $arrResult = Database::getInstance()->prepare($sql)->execute(...$restrictedPostals)->fetchAllAssoc();
                } else {
                    $arrResult = Database::getInstance()->prepare($sql)->execute()->fetchAllAssoc();
                }
            }
        } else {
            if ($searchString) {
                $sql = 'SELECT id, geox, geoy,releaseType, ' . self::getFilterSQLStringWeight() . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
//                $searchString = "%$searchString%";
                if (!empty($restrictedPostals)) {
                    $sql .= ' AND locationZip ' . C4GUtils::buildInString($restrictedPostals);
                }
                $searchString = $this->updateSearchStringForNonExactSearch($searchString);
                $whereClause = 'WHERE ' . self::getFilterSQLString();
                $sql .= ' ' . $whereClause;
                //$sql .= ' ORDER BY weight DESC';
                $sql .= ' HAVING weight > 2.5 ORDER BY weight DESC';

                if (!empty($restrictedPostals)) {
                    $arrResult = Database::getInstance()->prepare($sql)
                        ->execute(self::getFilterSQLValueSet($searchString), ...$restrictedPostals)
                        ->fetchAllAssoc();
                } else {
                    $arrResult = Database::getInstance()->prepare($sql)
                        ->execute(self::getFilterSQLValueSet($searchString))
                        ->fetchAllAssoc();
                }
            } else {
                $sql = "SELECT id, geox, geoy FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
                if (!empty($restrictedPostals)) {
                    $sql .= ' AND locationZip ' . C4GUtils::buildInString($restrictedPostals);
                }
                if (!empty($restrictedPostals)) {
                    $arrResult = Database::getInstance()->prepare($sql)->execute(...$restrictedPostals)->fetchAllAssoc();
                } else {
                    $arrResult = Database::getInstance()->prepare($sql)->execute()->fetchAllAssoc();
                }
            }
        }

        $arrIds = [];
        $arrLocations = [];

        $arrLocations[] = [
            floatval($userLocation[0]),
            floatval($userLocation[1]),
        ];
        foreach ($arrResult as $item) {
            if ($item['geox'] && $item['geoy']) {
                $arrIds[] = ['id' => $item['id']];
                $arrLocations[] = [
                    floatval($item['geox']),
                    floatval($item['geoy']),
                ];
            } else {
                // will be added after loading and adding the distance
                // this is required to not mess up the order when there are datasets without a location
                $missingIds[] = ['id' => $item['id']];
            }
        }

        if (is_array($userLocation)) {
            $areaService = new AreaService();
            $distances = [];

            $startPoint = $arrLocations[0];
            for ($i = 1; $i < count($arrLocations); $i++) {
                $distances[] = $areaService->calculateDistance($startPoint, $arrLocations[$i]);
            }

            foreach ($distances as $key => $distance) {
                $arrIds[$key]['distance'] = floatval($distance);
            }

            usort($arrIds, function ($a, $b) {
                if ($a['distance'] > $b['distance']) {
                    return 1;
                } elseif ($a['distance'] < $b['distance']) {
                    return -1;
                }

                return 0;
            });
        }

        foreach ($missingIds as $missingId) {
            $arrIds[] = $missingId;
        }

        return $arrIds;
    }

    private function loadAllIds($searchString)
    {
        if ($searchString) {
            $sql = 'SELECT id, ' . self::getFilterSQLStringWeight() . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
//            $searchString = "%$searchString%";
            $searchString = $this->updateSearchStringForNonExactSearch($searchString);
            $whereClause = 'AND ' . self::getFilterSQLString();
            $sql .= ' ' . $whereClause;
            //$sql .= ' ORDER BY weight DESC';
            $sql .= ' HAVING weight > 2.5 ORDER BY weight DESC';
            $arrResult = Database::getInstance()->prepare($sql)
                ->execute(self::getFilterSQLValueSet($searchString))
                ->fetchAllAssoc();
        } else {
            $sql = "SELECT id FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
            $arrResult = Database::getInstance()->execute($sql)->fetchAllAssoc();
        }
        $arrIds = [];
        foreach ($arrResult as $item) {
            $arrIds[] = $item['id'];
        }

        return $arrIds;
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

    private function convertDbResult($arrResult, $arrOptions = [])
    {
        $converter = new ShowcaseResultConverter();
        $data = $converter->convertDbResult($arrResult, $arrOptions);

        return $data;
    }

    private function getCacheKey($randKey, $filter, $sorting, $tagIds, $typeIds)
    {
        return sha1($randKey . '_' . $filter . '_' . $sorting . '_' . implode(',', $tagIds) . '_' . implode(',', $typeIds));
    }

    private function checkForCacheFile($key)
    {
        return $this->cache->hasCacheData($key);
    }

    private function writeIntoCache($key, $arrIds)
    {
        $this->cache->putCacheData($key, $arrIds);
    }

    private function getDataFromCache($key)
    {
        return $this->cache->getCacheData($key);
    }
}
