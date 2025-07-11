<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes;

use con4gis\CoreBundle\Classes\C4GUtils;
use Contao\Controller;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class WishlistInsertTag
{
    const TAG = 'wishlist';

    const TAG_PAYLOAD = ['items'];

    const COOKIE_WISHLIST = 'clientUuid';

    /**
     * @var RequestStack
     */
    private $requestStack = null;

    /**
     * WishlistInsertTag constructor.
     * @param RequestStack|null $requestStack
     */
    public function __construct(?RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param string $insertTag
     * @return string|bool
     */
    public function replaceWishlistTags(string $insertTag)
    {
        $arrTags = explode('::', $insertTag);
        if (count($arrTags) === 2 &&
            $arrTags[0] === self::TAG &&
            in_array($arrTags[1], self::TAG_PAYLOAD)
        ) {
            $request = $this->requestStack->getCurrentRequest();
            $clientUuid = $this->checkCookieForClientUuid($request);
            if ($clientUuid === null) {
                // no items found
                return '';
            }
            $db = Database::getInstance();
            $sql = 'SELECT * FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ? ORDER BY `tstamp` DESC LIMIT 3';
            $arrItems = $db->prepare($sql)->execute($clientUuid)->fetchAllAssoc();
            $arrItems = $this->convertToRealData($arrItems);
            $innerHTML = '';
            foreach ($arrItems as $arrItem) {
                $innerHTML .= $this->generateHTMLForItem($arrItem);
            }

            return $innerHTML;
        }

        return false;
    }

    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request->cookies->get(self::COOKIE_WISHLIST);

        return $clientUuidCookie === null ? null : $clientUuidCookie;
    }

    /**
     * Takes an array of wishlist items and converts them into a displayable structure.
     * @param $arrItems
     */
    private function convertToRealData($arrItems)
    {
        $db = Database::getInstance();
        $arrResult = [];
        foreach ($arrItems as $item) {
            $table = $item['dataTable'];
            $uuid = $item['dataUuid'];
            $sql = "SELECT * FROM $table WHERE `uuid` = ?";
            $entry = $db->prepare($sql)->execute($uuid)->fetchAssoc();
            if ($table === 'tl_gutesio_data_element') {
                $entry['internal_type'] = 'showcase';
            } else {
                $typeId = $entry['typeId'];
                $sql = 'SELECT * FROM tl_gutesio_data_child_type WHERE `uuid` = ?';
                $arrType = $db->prepare($sql)->execute($typeId)->fetchAssoc();
                $entry['internal_type'] = $arrType['type'];
            }

            $arrResult[] = $entry;
        }

        return $arrResult;
    }

    private function getImagePath($arrItem)
    {
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;
        $fileUtils = new FileUtils();
        $imagePath = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrItem['imageCDN'], '-small',600);

        return $imagePath;
    }

    /**
     * Generates HTML output for one item on the wishlist.
     * @param $arrItem
     */
    private function generateHTMLForItem($arrItem)
    {
        $name = $arrItem['name'];
        $imagePath = $this->getImagePath($arrItem);
        $deleteRoute = '/gutesio/operator/wishlist/remove/' . $arrItem['uuid'];
        $arrItem['uuid'] = str_replace(['{', '}'], ['', ''], $arrItem['uuid']);
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        if ($arrItem['internal_type'] === 'product') {
            $detailRoute = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->productDetailPage . '}}') . '/' . $arrItem['uuid'];
            $resultHtml = '<div class="row mt-4 wishlistItem">
                <div class="col-4">
                    <img class="product img-fluid"
                         src="' . $imagePath . '">
                </div>
                <div class="col-8">
                    <div class="title">' . $name . '</div>
                    <div class="price">' . $arrItem['price'] . '</div>
    
                    <div class="row mt-2">
                        <div class="col-6">
    
                            <a href="' . $detailRoute . '" class="btn btn-sm">Mehr <i class="fas fa-angle-right"></i>
                            </a>
    
                        </div>
    
                    </div>
    
    
                </div>
            </div>';
        } elseif ($arrItem['internal_type'] === 'showcase') {
            $postal = $arrItem['contactZip'] ? $arrItem['contactZip'] : $arrItem['locationZip'];
            $city = $arrItem['contactCity'] ? $arrItem['contactCity'] : $arrItem['locationCity'];
            $detailRoute = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->showcaseDetailPage . '}}') . '/' . $arrItem['alias'];
            $resultHtml = '<div class="row mt-4 wishlistItem">
                <div class="col-4">
                    <img class="product img-fluid"
                         src="' . $imagePath . '">
                </div>
                <div class="col-8">
                    <div class="title">' . $name . '</div>
                    <div class="row mt-2">
                        <div class="col-6">
    
                            <a href="' . $detailRoute . '" class="btn btn-sm">Mehr <i class="fas fa-angle-right"></i>
                            </a>
    
                        </div>
    
                    </div>
    
    
                </div>
            </div>';
        } elseif ($arrItem['internal_type'] === 'job') {
            $detailRoute = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->jobDetailPage . '}}') . '/' . $arrItem['uuid'];
            $resultHtml = '<div class="row mt-4 wishlistItem">
                <div class="col-4">
                    <img class="product img-fluid"
                         src="' . $imagePath . '">
                </div>
                <div class="col-8">
                    <div class="title">' . $name . '</div>
    
                    <div class="row mt-2">
                        <div class="col-6">
    
                            <a href="' . $detailRoute . '" class="btn btn-sm">Mehr <i class="fas fa-angle-right"></i>
                            </a>
    
                        </div>
    
                    </div>
    
    
                </div>
            </div>';
        } elseif ($arrItem['internal_type'] === 'event') {
            $detailRoute = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->eventDetailPage . '}}') . '/' . $arrItem['uuid'];
            $resultHtml = '<div class="row mt-4 wishlistItem">
                <div class="col-4">
                    <img class="product event img-fluid"
                         src="' . $imagePath . '">
                </div>
                <div class="col-8">
                    <div class="title">' . $name . '</div>
    
                    <div class="row mt-2">
                        <div class="col-6">
    
                            <a href="' . $detailRoute . '" class="btn btn-sm">Mehr <i class="fas fa-angle-right"></i>
                            </a>
    
                        </div>
    
                    </div>
    
    
                </div>
            </div>';
        } elseif ($arrItem['internal_type'] === 'arrangement') {
            $detailRoute = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->arrangementDetailPage . '}}') . '/' . $arrItem['uuid'];
            $resultHtml = '<div class="row mt-4 wishlistItem">
                <div class="col-4">
                    <img class="product arrangement img-fluid"
                         src="' . $imagePath . '">
                </div>
                <div class="col-8">
                    <div class="title">' . $name . '</div>
    
                    <div class="row mt-2">
                        <div class="col-6">
    
                            <a href="' . $detailRoute . '" class="btn btn-sm">Mehr <i class="fas fa-angle-right"></i>
                            </a>
    
                        </div>
    
                    </div>
    
    
                </div>
            </div>';
        } elseif ($arrItem['internal_type'] === 'service') {
            $detailRoute = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->serviceDetailPage . '}}') . '/' . $arrItem['uuid'];
            $resultHtml = '<div class="row mt-4 wishlistItem">
                <div class="col-4">
                    <img class="product service img-fluid"
                         src="' . $imagePath . '">
                </div>
                <div class="col-8">
                    <div class="title">' . $name . '</div>
    
                    <div class="row mt-2">
                        <div class="col-6">
    
                            <a href="' . $detailRoute . '" class="btn btn-sm">Mehr <i class="fas fa-angle-right"></i>
                            </a>
    
                        </div>
    
                    </div>
    
    
                </div>
            </div>';
        } elseif ($arrItem['internal_type'] === 'person') {
            $detailRoute = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->personDetailPage . '}}') . '/' . $arrItem['uuid'];
            $resultHtml = '<div class="row mt-4 wishlistItem">
                <div class="col-4">
                    <img class="product person img-fluid"
                         src="' . $imagePath . '">
                </div>
                <div class="col-8">
                    <div class="title">' . $name . '</div>
    
                    <div class="row mt-2">
                        <div class="col-6">
    
                            <a href="' . $detailRoute . '" class="btn btn-sm">Mehr <i class="fas fa-angle-right"></i>
                            </a>
    
                        </div>
    
                    </div>
    
    
                </div>
            </div>';
        } elseif ($arrItem['internal_type'] === 'voucher') {
            $detailRoute = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->voucherDetailPage . '}}') . '/' . $arrItem['uuid'];
            $resultHtml = '<div class="row mt-4 wishlistItem">
                <div class="col-4">
                    <img class="product voucher img-fluid"
                         src="' . $imagePath . '">
                </div>
                <div class="col-8">
                    <div class="title">' . $name . '</div>
    
                    <div class="row mt-2">
                        <div class="col-6">
    
                            <a href="' . $detailRoute . '" class="btn btn-sm">Mehr <i class="fas fa-angle-right"></i>
                            </a>
    
                        </div>
    
                    </div>
    
    
                </div>
            </div>';
        }

        return $resultHtml;
    }
}
