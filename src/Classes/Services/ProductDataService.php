<?php

namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use Contao\Database;
use gutesio\OperatorBundle\Classes\Helper\OfferDataHelper;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

class ProductDataService
{

    public function __construct(private OfferDataHelper $helper)
    {
    }

    public function getProductData(
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
                tl_gutesio_data_element.alias as vendorAlias, ' . '
                p.price, p.strikePrice, p.priceStartingAt, p.priceReplacer, '.
            'p.tax as taxNote, p.discount, p.color, p.size, p.availableAmount, p.basePriceUnit, p.basePriceUnitPerPiece, p.basePriceRequired, '.
            'p.allergenes, p.ingredients, p.kJ, p.fat, p.saturatedFattyAcid, p.carbonHydrates, p.sugar, p.salt, '.
            'p.isbn, p.ean, p.brand, '.
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
                LEFT JOIN tl_gutesio_data_child_product p ON p.childId = a.uuid ' . '
                WHERE a.published = 1 AND tl_gutesio_data_child_type.type = "product"'  .
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
        if ($filterData['location']) {
            $sql .= " AND (tl_gutesio_data_element.locationCity LIKE ? OR tl_gutesio_data_element.locationZip LIKE ?)";
            $parameters[] = $filterData['location'];
            $parameters[] = $filterData['location'];
        }

        $sql .= $this->helper->getOrderClause($filterData, $offset, $limit);

        $productData = $database->prepare($sql)->execute(...$parameters)->fetchAllAssoc();

        $offerTagRelations = $this->helper->loadOfferTagRelations($productData);

        $formattedData = $this->formatProductData($productData, $tags, $offerTagRelations);

        return $formattedData;
    }

    private function formatProductData(array $products, $tags, $offerTagRelations)
    {
        foreach ($products as $key => $product) {
            $product['rawPrice'] = $product['price'];
            if ($product['strikePrice'] > 0 && $product['strikePrice'] > $product['price']) {
                $product['strikePrice'] =
                    number_format(
                        $product['strikePrice'] ?: 0,
                        2,
                        ',',
                        ''
                    ) . ' €*';
                if ($product['priceStartingAt']) {
                    $product['strikePrice'] =
                        $GLOBALS['TL_LANG']['offer_list']['frontend']['startingAt'] .
                        ' ' . $product['strikePrice'];
                }
            } else {
                unset($product['strikePrice']);
            }
            if (!empty($product['priceReplacer'])) {
                $product['price'] =
                    $GLOBALS['TL_LANG']['offer_list']['price_replacer_options'][$product['priceReplacer']];
            } elseif ((!$product['price'])) {
                $product['price'] =
                    $GLOBALS['TL_LANG']['offer_list']['price_replacer_options']['free'];
            } else {
                $product['price'] =
                    number_format(
                        $product['price'] ?: 0,
                        2,
                        ',',
                        ''
                    ) . ' €';
                if ($product['price'] > 0) {
                    $product['price'] .= '*';
                }
                if ($product['priceStartingAt']) {
                    $product['price'] =
                        $GLOBALS['TL_LANG']['offer_list']['frontend']['startingAt'] .
                        ' ' . $product['price'];
                }
            }

            $product['color'] = $product['color'] ?: '';
            $product['size'] = $product['size'] ?: '';
            $product['isbn'] = $product['isbn'] ?: '';
            $product['ean'] = $product['ean'] ?: '';
            $product['brand'] = $product['brand'] ?: '';
            $product['basePriceUnit'] = $product['basePriceUnit'] ?: '';
            $product['basePriceUnitPerPiece'] = $product['basePriceUnitPerPiece'] ?: '';
            $product['basePriceRequired'] = $product['basePriceRequired'] ?: false;
            $product['availableAmount'] = $product['availableAmount'] ?: '';

            if ($product['basePriceRequired']) {
                $product['basePrice'] = $product['rawPrice'] && $product['size'] && $product['basePriceUnitPerPiece'] ? $product['rawPrice'] / $product['size'] * $product['basePriceUnitPerPiece'] : '';
                $product['basePrice'] = number_format(
                        $product['basePrice'] ?: 0,
                        2,
                        ',',
                        ''
                    ) . ' €';
            }
            $product['allergenes'] = $product['allergenes'] ?: '';
            $product['ingredients'] = $product['ingredients'] ?: '';
            $product['kJ'] = $product['kJ'] ?: '';
            $product['fat'] = $product['fat'] ?: '';
            $product['saturatedFattyAcid'] = $product['saturatedFattyAcid'] ?: '';
            $product['carbonHydrates'] = $product['carbonHydrates'] ?: '';
            $product['sugar'] = $product['sugar'] ?: '';
            $product['salt'] = $product['salt'] ?: '';

            $settings = GutesioOperatorSettingsModel::findSettings();
            switch ($product['taxNote']) {
                case 'regular':
                    $product['taxNote'] = sprintf(
                        $GLOBALS['TL_LANG']['offer_list']['frontend']['details']['taxInfo'],
                        ($settings->taxRegular ?: '19') . '%'
                    );

                    break;
                case 'reduced':
                    $product['taxNote'] = sprintf(
                        $GLOBALS['TL_LANG']['offer_list']['frontend']['details']['taxInfo'],
                        ($settings->taxReduced ?: '7') . '%'
                    );

                    break;
                case 'none':
                    $product['taxNote'] =
                        $GLOBALS['TL_LANG']['offer_list']['frontend']['details']['noTaxInfo'];

                    break;
                default:
                    $product['taxNote'] =
                        $GLOBALS['TL_LANG']['offer_list']['frontend']['list']['taxInfo'];

                    break;
            }

            $product = $this->helper->setImageAndDetailLinks($product);
            $product['tagLinks'] = $this->helper->generateTagLinks($tags, $offerTagRelations[$product['uuid']]);

            $products[$key] = $product;
        }

        return $products;
    }
}