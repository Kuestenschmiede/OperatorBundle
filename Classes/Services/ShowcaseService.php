<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use con4gis\MapsBundle\Resources\contao\models\C4gMapProfilesModel;
use con4gis\MapsBundle\Classes\Services\AreaService;
use Contao\Database;
use Contao\StringUtil;
use gutesio\DataModelBundle\Classes\ShowcaseResultConverter;
use gutesio\DataModelBundle\Classes\TagDetailFieldGenerator;
use gutesio\DataModelBundle\Classes\TagFieldUtil;
use gutesio\DataModelBundle\Classes\TypeFieldUtil;
use gutesio\OperatorBundle\Classes\Cache\ShowcaseListApiCache;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

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

    const FILTER_SQL_STRING = '(`name` LIKE ? OR `description` LIKE ? OR `contactName` LIKE ? OR ' .
                                '`contactStreet` LIKE ? OR `contactCity` LIKE ? OR `locationStreet` LIKE ? OR `locationCity` LIKE ?)';

    const FILTER_SQL_STRING_WEIGHT = 'IF (`name` LIKE ?, 50, 0) + IF (`description` LIKE ?, 20, 0) + ' .
                                        'IF (`contactName` LIKE ?, 20, 0) + IF (`contactStreet` LIKE ?, 5,0) + IF (`contactCity` LIKE ?,5, 0) + ' .
                                            'IF (`locationStreet` LIKE ?, 5, 0) + IF (`locationCity` LIKE ?, 5, 0) AS weight';

    private $filterConnector = 'AND';

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
                    $relatedUuids = StringUtil::deserialize($relatedShowcase['showcaseIds']);
                    if (in_array($arrShowcase['uuid'], $relatedUuids)) {
                        $returnData[] = $relatedShowcase;
                    }
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
        $tagIds = []
    ) {
        $searchString = $params['filter'] ?: '';
        $sorting = $params['sorting'] ?: '';
        $randKey = $params['randKey'];
        $position = explode(',', $params['pos']);
        $key = $this->getCacheKey($randKey, $searchString, $sorting, $tagIds);
        if ($this->checkForCacheFile($key)) {
            $arrIds = $this->getDataFromCache($key);
            if ($arrIds && is_array($arrIds)) {
                // slice correct chunk
                $arrIds = array_slice($arrIds, $offset, $limit);
                if (count($arrIds) > 0) {
                    $arrResult = $this->loadByIds($arrIds, $params['sorting'] === 'distance');
                } else {
                    $arrResult = [];
                }
            } else {
                // error
                $arrResult = [];
            }
        } else {
            $execQuery = true;
            if ($sorting) {
                switch ($sorting) {
                    case 'random':
                        $arrIds = $this->generateRandomSortingMap($searchString, $typeIds, $tagIds);
                        $this->writeIntoCache($this->getCacheKey($randKey, $searchString, $sorting, $tagIds), $arrIds);
                        $arrIds = array_slice($arrIds, $offset, $limit);
                        if (count($arrIds) > 0) {
                            $arrResult = $this->loadByIds($arrIds);
                        } else {
                            $arrResult = [];
                        }
                        $execQuery = false;

                        break;
                    case 'distance':
                        $arrIdsWithDistances = $this->generateDistanceSortingMap($position, $searchString, $typeIds, $tagIds);
                        $this->writeIntoCache($this->getCacheKey($randKey, $searchString, $sorting, $tagIds), $arrIdsWithDistances);
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
                $elementIdString = $this->createIdStringForElements($typeIds, $searchString, $tagIds);
                if ($elementIdString !== '()' && $searchString) {
                    $sql = 'SELECT *, ' . self::FILTER_SQL_STRING_WEIGHT . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') ";
                    $sql .= 'AND `uuid` IN ' . $elementIdString . ' AND (' . self::FILTER_SQL_STRING . ')';
                    $additionalOrderBy = ' weight';
                    $insertSearchParams = true;
                } elseif ($elementIdString !== '()') {
                    $sql = "SELECT * FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') ";
                    $sql .= 'AND `uuid` IN ' . $elementIdString;
                    $insertSearchParams = false;
                } elseif ($searchString) {
                    $sql = 'SELECT *, ' . self::FILTER_SQL_STRING_WEIGHT . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') ";
                    $sql .= 'AND ' . self::FILTER_SQL_STRING;
                    $additionalOrderBy = ' weight';
                    $insertSearchParams = true;
                } else {
                    $sql = "SELECT * FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') ";
                    $insertSearchParams = false;
                }
                $sortClause = '';
                if ($sorting) {
                    $arrSort = explode('_', $sorting);
                    if ($searchString && ($sorting == 'random')) {
                        $sortClause = ' ORDER BY weight DESC ';
                    } elseif ($sorting == 'random') {
                        $sortClause = ' ORDER BY RAND() ';
                    } else {
                        $sortClause = " ORDER BY {$arrSort[0]} " . strtoupper($arrSort[1]);
                    }
                }

                $sql .= $sortClause . ' LIMIT ? OFFSET ?';
                $stm = Database::getInstance()->prepare($sql);
                $searchString = '%' . $searchString . '%';
                if ($insertSearchParams) {
                    $arrResult = $stm->execute(
                        $searchString, $searchString, $searchString, $searchString, $searchString,
                        $searchString, $searchString, $searchString, $searchString, $searchString,
                        $searchString, $searchString, $searchString, $searchString,
                        $limit, $offset)->fetchAllAssoc();
                } else {
                    $arrResult = $stm->execute($limit, $offset)->fetchAllAssoc();
                }
            }
        }

        return $this->convertDbResult($arrResult, ['loadTagsComplete' => true]);
    }

    private function formatDistance($distanceInMeters)
    {
        if (!$distanceInMeters) {
            return $distanceInMeters;
        }
        $unit = ' km';
        if ($distanceInMeters < 1) {
            $distanceInMeters = $distanceInMeters * 1000;
            $unit = ' m';
        } else {
            $distanceInMeters = number_format($distanceInMeters, 2);
        }

        return $distanceInMeters . $unit;
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

    public function loadByAlias($alias) : array
    {
        $arrResult = Database::getInstance()
            ->prepare('SELECT * FROM tl_gutesio_data_element ' .
                "WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') AND alias = ?")
            ->execute($alias)->fetchAllAssoc();
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

        return $returnData;
    }

    /**
     * Loads all showcase IDs and maps them to a random number used for sorting.
     */
    private function generateRandomSortingMap(
        $searchString,
        $typeIds = [],
        $tagIds = []
    ) : array {
        $arrIds = $this->loadByTypes($typeIds, $searchString, $tagIds);
        if (!$searchString) {
            shuffle($arrIds);
        }

        return $arrIds;
    }

    private function loadByTypes($typeIds, $searchString = '', $tagIds = [])
    {
        $db = Database::getInstance();
        $elementIdString = $this->createIdStringForElements($typeIds, $searchString, $tagIds);
        if ($searchString !== '' || $elementIdString === '()') {
            // avoid executing broken query when no search string is given
            if ($searchString === '') {
                return [];
            }
            $sql = 'SELECT `id`, ' . self::FILTER_SQL_STRING_WEIGHT . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
        } else {
            if ($searchString) {
                $sql = 'SELECT `id`, ' . self::FILTER_SQL_STRING_WEIGHT . ' FROM tl_gutesio_data_element ' .
                    "WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') AND `uuid` IN " . $elementIdString;
            } else {
                $sql = 'SELECT `id` FROM tl_gutesio_data_element ' .
                    "WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '') AND `uuid` IN " . $elementIdString;
            }
        }
        if ($searchString) {
            $this->filterConnector = 'AND'; // todo temporary fix for filter
            $sql .= ' ' . $this->filterConnector . ' ';
        }

        if ($searchString) {
            $searchString = "%$searchString%";
            // connect with OR here since the other condition is already considering the filter
            $whereClause = self::FILTER_SQL_STRING;
            $sql .= ' ' . $whereClause;
            $sql .= ' ORDER BY weight DESC';
            $arrResult = Database::getInstance()->prepare($sql)
                ->execute(
                    $searchString, $searchString, $searchString, $searchString, $searchString,
                    $searchString, $searchString, $searchString, $searchString, $searchString,
                    $searchString, $searchString, $searchString, $searchString)->fetchAllAssoc();
            // search for type name matches
            $typeResult = Database::getInstance()->prepare('SELECT `tl_gutesio_data_element`.`id` FROM `tl_gutesio_data_element` ' .
                'JOIN `tl_gutesio_data_element_type` ON `tl_gutesio_data_element_type`.`elementId` = `tl_gutesio_data_element`.`uuid` ' .
                'JOIN `tl_gutesio_data_type` ON `tl_gutesio_data_element_type`.`typeId` = `tl_gutesio_data_type`.`uuid` ' .
                'WHERE `tl_gutesio_data_type`.`name` LIKE ?'
            )->execute($searchString)->fetchAllAssoc();

            $arrResult = array_merge($arrResult, $typeResult);
        } else {
            $arrResult = Database::getInstance()->execute($sql)->fetchAllAssoc();
        }
        $returnIds = [];
        foreach ($arrResult as $result) {
            if (!in_array($result['id'], $returnIds)) {
                $returnIds[] = $result['id'];
            }
        }

        return $returnIds;
    }

    private function createIdStringForElements(
        $typeIds,
        $searchString,
        $tagIds = []
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
        $sql = 'SELECT DISTINCT `elementId` FROM tl_gutesio_data_element_type JOIN tl_gutesio_data_element ON tl_gutesio_data_element_type.elementId = tl_gutesio_data_element.uuid';
        if ($idString !== '()') {
            $sql .= ' WHERE tl_gutesio_data_element_type.`typeId` IN ' . $idString;
            if ($searchString !== '') {
                $sql .= ' AND tl_gutesio_data_element.`name` LIKE ?';
            }
        }
        // get element ids connected to valid types (type name is already checked here)
        if ($idString === '()' && $searchString !== '') {
            // no id constraint, but search constraint -> do not load everything
//            $arrElements = [];
            $arrElements = $db->prepare($sql)->execute('%' . $searchString . '%')->fetchAllAssoc();
        } else if ($idString !== '()' && $searchString !== '') {
            // id constraint & search constraint
            $arrElements = $db->prepare($sql)->execute('%' . $searchString . '%')->fetchAllAssoc();
        } else {
            $arrElements = $db->prepare($sql)->execute()->fetchAllAssoc();
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
        if ($tagIdString === '()') {
            // no tagId constraint but search string, do not load everything
            $arrTaggedElements = [];
        } else {
            $arrTaggedElements = $db->prepare($sql)->execute()->fetchAllAssoc();
        }

        if ($searchString) {
            $additionalIds = $this->getIdsForTagAndTypeValues($searchString);
            $this->filterConnector = 'OR';
        } else {
            $additionalIds = [];
        }

        $tagConstraintOnly = (count($tagIds) > 0)
            && (count($arrTaggedElements) > 0)
            && (count($arrElements) === 0);
        $tagAndTypeConstraint = count($arrElements) > 0
            && count($arrTaggedElements) > 0;

        if ($tagConstraintOnly) {
            $arrElements = $arrTaggedElements;
        }
        $elementIdString = '(';

        if ($tagAndTypeConstraint) {
            foreach ($arrElements as $key => $element) {
                // check if $element['elementId'] === $taggedElement['elementId']
                $found = false;
                foreach ($arrTaggedElements as $taggedElement) {
                    if ($taggedElement['elementId'] === $element['elementId']) {
                        $found = true;

                        break;
                    }
                }
                if ($found) {
                    $elementIdString .= '"' . $element['elementId'] . '"';
                    if (array_key_last($arrElements) !== $key) {
                        $elementIdString .= ',';
                    }
                }
            }
        } else {
            // only type constraints are given
            // or only tag constraints are given
            // handling is identical
            foreach ($arrElements as $key => $arrElement) {
                $elementIdString .= "\"{$arrElement['elementId']}\"";
                if (array_key_last($arrElements) !== $key) {
                    $elementIdString .= ',';
                }
            }
        }

        foreach ($additionalIds as $key => $additionalId) {
            if (strpos($elementIdString, $additionalId['elementId']) === false) {
                $elementIdString .= "\"{$additionalId['elementId']}\"";
                if (array_key_last($arrElements) !== $key) {
                    $elementIdString .= ',';
                }
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
            $sql .= "(`tagFieldKey` = '$tagFieldName' AND `tagFieldValue` LIKE ?)";
            if (array_key_last($tagFieldNames) !== $key) {
                $sql .= ' OR ';
            }
            $ctr++;
        }
        $parameters = [];
        for ($i = 0; $i < $ctr; $i++) {
            $parameters[] = '%' . $searchString . '%';
        }

        $arrTagIds = [];
        if (count($parameters) > 0) {
            $arrTagIds = Database::getInstance()->prepare($sql)->execute($parameters)->fetchAllAssoc();
            $arrTagIds = array_values($arrTagIds);
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
            $parameters[] = '%' . $searchString . '%';
        }

        $arrTypeIds = [];
        if (count($parameters) > 0) {
            $arrTypeIds = Database::getInstance()->prepare($sql)->execute($parameters)->fetchAllAssoc();
            $arrTypeIds = array_values($arrTypeIds);
        }

        return array_unique(array_merge($arrTagIds, $arrTypeIds));
    }

    private function generateDistanceSortingMap($userLocation, $searchString, $typeIds = [], $tagIds = [])
    {
        $elementIdString = $this->createIdStringForElements($typeIds, $searchString, $tagIds);
        if ($elementIdString !== '()') {
            if ($searchString) {
                $sql = 'SELECT id, geox, geoy,releaseType, ' . self::FILTER_SQL_STRING_WEIGHT . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
                $sql .= ' AND `uuid` IN ' . $elementIdString;
                $searchString = "%$searchString%";
                $whereClause = 'AND (' . self::FILTER_SQL_STRING . ')';
                $sql .= ' ' . $whereClause;
                $arrResult = Database::getInstance()->prepare($sql)
                    ->execute(
                        $searchString, $searchString, $searchString, $searchString, $searchString,
                        $searchString, $searchString, $searchString, $searchString, $searchString,
                        $searchString, $searchString, $searchString, $searchString)
                    ->fetchAllAssoc();
            } else {
                $sql = "SELECT id, geox, geoy, releaseType FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
                $sql .= ' AND `uuid` IN ' . $elementIdString;
                $arrResult = Database::getInstance()->execute($sql)->fetchAllAssoc();
            }
        } else {
            if ($searchString) {
                $sql = 'SELECT id, geox, geoy,releaseType, ' . self::FILTER_SQL_STRING_WEIGHT . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
                $searchString = "%$searchString%";
                $whereClause = 'WHERE ' . self::FILTER_SQL_STRING;
                $sql .= ' ' . $whereClause;
                $sql .= ' ORDER BY weight DESC'; //TEST
                $arrResult = Database::getInstance()->prepare($sql)
                    ->execute(
                        $searchString, $searchString, $searchString, $searchString, $searchString,
                        $searchString, $searchString, $searchString, $searchString, $searchString,
                        $searchString, $searchString, $searchString, $searchString)
                    ->fetchAllAssoc();
            } else {
                $sql = "SELECT id, geox, geoy FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
                $arrResult = Database::getInstance()->execute($sql)->fetchAllAssoc();
            }
        }

        $arrIds = [];
        $arrLocations = [];
        $arrLocations[] = [
            floatval($userLocation[0]),
            floatval($userLocation[1]),
        ];
        foreach ($arrResult as $item) {
            if ($item['geox'] && $item['geoy'] && $item['releaseType'] !== 'interregional') {
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

        $areaService = new AreaService();
        $settings = GutesioOperatorSettingsModel::findSettings();
        $mapsProfileId = $settings->detail_profile;
        $mapsProfile = C4gMapProfilesModel::findByPk($mapsProfileId);
        $matrixResponse = $areaService->performMatrix($mapsProfile, 'driving', $arrLocations);

        try {
            $distanceResult = \GuzzleHttp\json_decode($matrixResponse);
        } catch (\Exception $exception) {
            C4gLogModel::addLogEntry('operator', "Fehler beim json_decode der Matrix-Response '$matrixResponse'.");

            return $arrIds;
        }
        $distances = $distanceResult->sources_to_targets;
        $distances = $distances[0];
        foreach ($distances as $key => $distance) {
//            if ($key === 0) {
//                // start point
//                $arrIds[$key]['distance'] = 0;
//                continue;
//            }
            $arrIds[$key]['distance'] = floatval($distance->distance);
        }

        usort($arrIds, function ($a, $b) {
            if ($a['distance'] > $b['distance']) {
                return 1;
            } elseif ($a['distance'] < $b['distance']) {
                return -1;
            }

            return 0;
        });
        foreach ($missingIds as $missingId) {
            $arrIds[] = $missingId;
        }

        return $arrIds;
    }

    private function loadAllIds($searchString)
    {
        if ($searchString) {
            $sql = 'SELECT id, ' . self::FILTER_SQL_STRING_WEIGHT . " FROM tl_gutesio_data_element WHERE (releaseType = '" . self::INTERNAL . "' OR releaseType = '" . self::INTER_REGIONAL . "' OR releaseType = '')";
            $searchString = "%$searchString%";
            $whereClause = 'AND ' . self::FILTER_SQL_STRING;
            $sql .= ' ' . $whereClause;
            $sql .= ' ORDER BY weight DESC'; //TEST
            $arrResult = Database::getInstance()->prepare($sql)
                ->execute(
                    $searchString, $searchString, $searchString, $searchString, $searchString,
                    $searchString, $searchString, $searchString, $searchString, $searchString,
                    $searchString, $searchString, $searchString, $searchString)
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

    private function convertDbResult($arrResult, $arrOptions = [])
    {
        $converter = new ShowcaseResultConverter();
        $data = $converter->convertDbResult($arrResult, $arrOptions);

        return $data;
    }

    private function getCacheKey($randKey, $filter, $sorting, $tagIds)
    {
        return sha1($randKey . '_' . $filter . '_' . $sorting . '_' . implode(',', $tagIds));
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
