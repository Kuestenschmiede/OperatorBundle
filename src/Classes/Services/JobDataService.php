<?php

namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use Contao\Database;
use gutesio\OperatorBundle\Classes\Helper\OfferDataHelper;
use Carbon\Carbon;

class JobDataService
{
    public function __construct(private OfferDataHelper $helper)
    {
    }

    public function getJobData(
        string $searchTerm,
        int $offset,
        array $filterData,
        int $limit,
        array $tags
    ) {
        $database = Database::getInstance();
        $parameters = [];
        $termsSet = ($searchTerm !== "") && ($searchTerm !== "*");
        $strTagFieldClause = " tl_gutesio_data_child_tag_values.`tagFieldValue` LIKE ?";
        $sqlExtendedCategoryTerms = " OR tl_gutesio_data_child_type.extendedSearchTerms LIKE ?";

        $sql = 'SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
            'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, a.releasedAt, a.alias, ' . '
                COALESCE(a.shortDescription) AS shortDescription, ' . '
                tl_gutesio_data_child_type.uuid AS typeId, tl_gutesio_data_child_type.type AS type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                tl_gutesio_data_element.name as vendorName, ' . '
                tl_gutesio_data_element.alias as vendorAlias, ' . '
                tl_gutesio_data_element.locationCity as locationCity, ' .
                'j.beginDate as beginDate, '.
                'j.workHours as workHours, '.
                'j.remoteType as remoteType, '.
                'j.jobBenefits as jobBenefits, '.
                'j.applicationContactUrl as applicationContactUrl, '.
                'j.applicationContactEMail as applicationContactEMail, '.
                'j.applicationContactPhone as applicationContactPhone, '.
            (
            $termsSet ?
                'match(a.fullTextContent) against(\'' . $searchTerm . '\' in boolean mode) as relevance, '
                : ""
            ) .
            'a.uuid as uuid, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag ON tl_gutesio_data_child_tag.childId = a.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_job j ON j.childId = a.uuid ' . '
                WHERE a.published = 1 AND tl_gutesio_data_child_type.type = "job"'  .
            (
            $termsSet ?
                ' AND (match(a.fullTextContent) against(\'' . $searchTerm . '\' in boolean mode) OR ' . $strTagFieldClause . $sqlExtendedCategoryTerms . ') '
                : ""
            ) .
            ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())'
        ;

        if ($termsSet) {
            $searchTermParam = str_replace("*", "%", $searchTerm);
            $parameters[] = "%".$searchTermParam;
            $parameters[] = "%".$searchTermParam;
        }

        $res = $this->helper->handleFilter($filterData, $parameters, $sql);
        $parameters = $res['params'];
        $sql = $res['sql'];

        $sql .= $this->helper->getOrderClause($filterData, $offset, $limit);

        $jobData = $database->prepare($sql)->execute(...$parameters)->fetchAllAssoc();

        $offerTagRelations = $this->helper->loadOfferTagRelations($jobData);

        $formattedData = $this->formatJobData($jobData, $tags, $offerTagRelations);

        return $formattedData;
    }

    private function formatJobData($jobs, $tags, $offerTagRelations)
    {
        foreach ($jobs as $key => $job) {

            $job = $this->helper->setImageAndDetailLinks($job);

            $job['beginDateJob'] = '';
            if (!key_exists('beginDate', $job) || !$job['beginDate'] || (time() > intval($job['beginDate']))) {
                $job['beginDateJob'] = 'sofort';
            } else if (key_exists('beginDate', $job) || $job['beginDate']) {
                $job['beginDateJob'] = 'zum '.date('d.m.Y', $job['beginDate']);
            }

            $job['tagLinks'] = $this->helper->generateTagLinks($tags, $offerTagRelations[$job['uuid']]);

            if ($job['vendorName'] && $job['vendorAlias']) {
                $job = $this->helper->setImageAndDetailLinks($job);
            }

            if (key_exists('remoteType', $job)) {
                switch ($job['remoteType']) {
                    case 1:
                        $job['remoteTypeDisplay'] = 'Nur vor Ort';
                        break;
                    case 2:
                        $job['remoteTypeDisplay'] = '100% Remote';
                        break;
                    case 3:
                        $job['remoteTypeDisplay'] = 'Hybrid';
                        break;
                    default:
                        break;
                }
            }

            $jobs[$key] = $job;
        }

        return $jobs;
    }
}