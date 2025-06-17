<?php

namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use Contao\Database;
use gutesio\OperatorBundle\Classes\Helper\OfferDataHelper;

class PersonDataService
{
    public function __construct(private OfferDataHelper $helper)
    {
    }

    public function getPersonData(
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
            'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                COALESCE(a.shortDescription) AS shortDescription, ' . '
                tl_gutesio_data_child_type.uuid AS typeId, tl_gutesio_data_child_type.type AS type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                tl_gutesio_data_element.name as vendorName, ' . '
                tl_gutesio_data_element.alias as vendorAlias, ' .
            'p.dateOfBirth, p.dateOfDeath, '.
            (
            $termsSet ?
                'match(a.fullTextContent) against(\'' . $searchTerm . '\' in boolean mode) as relevance, '
                : ""
            ) .
            'a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag ON tl_gutesio_data_child_tag.childId = a.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_person p ON p.childId = a.uuid ' . '
                WHERE a.published = 1 AND tl_gutesio_data_child_type.type = "person"'  .
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

        $personData = $database->prepare($sql)->execute(...$parameters)->fetchAllAssoc();

        $offerTagRelations = $this->helper->loadOfferTagRelations($personData);

        $formattedData = $this->formatPersonData($personData, $tags, $offerTagRelations);

        return $formattedData;
    }

    private function formatPersonData($persons, $tags, $offerTagRelations)
    {
        foreach ($persons as $key => $person) {
            $person = $this->helper->setImageAndDetailLinks($person);

            $person['tagLinks'] = $this->helper->generateTagLinks($tags, $offerTagRelations[$person['uuid']]);

            $persons[$key] = $person;
        }

        return $persons;
    }
}