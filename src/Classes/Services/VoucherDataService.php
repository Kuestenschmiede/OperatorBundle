<?php

namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use Contao\Database;
use gutesio\OperatorBundle\Classes\Helper\OfferDataHelper;

class VoucherDataService
{
    public function __construct(private OfferDataHelper $helper)
    {
    }

    public function getVoucherData(
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
            'v.minCredit, v.maxCredit, v.credit, v.customizableCredit, '.
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
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_voucher v ON v.childId = a.uuid ' . '
                WHERE a.published = 1 AND tl_gutesio_data_child_type.type = "voucher"'  .
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

        if ($filterData['tags']) {
            $sql .= " AND tl_gutesio_data_child_tag_values.tagId " . C4GUtils::buildInString($filterData['tags']);
            $parameters = array_merge($parameters, $filterData['tags']);
        }
        if ($filterData['categories']) {
            $sql .= " AND typeId " . C4GUtils::buildInString($filterData['categories']);
            $parameters = array_merge($parameters, $filterData['categories']);
        }

        $sql .= $this->helper->getOrderClause($filterData, $offset, $limit);

        $voucherData = $database->prepare($sql)->execute(...$parameters)->fetchAllAssoc();

        $offerTagRelations = $this->helper->loadOfferTagRelations($voucherData);

        $formattedData = $this->formatVoucherData($voucherData, $tags, $offerTagRelations);

        return $formattedData;
    }

    private function formatVoucherData($vouchers, $tags, $offerTagRelations)
    {
        foreach ($vouchers as $key => $voucher) {
            $voucher = $this->helper->setImageAndDetailLinks($voucher);

            $voucher['tagLinks'] = $this->helper->generateTagLinks($tags, $offerTagRelations[$voucher['uuid']]);

            $vouchers[$key] = $voucher;
        }

        return $vouchers;
    }
}