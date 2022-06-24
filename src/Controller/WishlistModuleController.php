<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Controller;


use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\ResourceLoader;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel;
use con4gis\FrameworkBundle\Classes\Conditions\FieldNotValueCondition;
use con4gis\FrameworkBundle\Classes\Conditions\FieldValueCondition;
use con4gis\FrameworkBundle\Classes\Conditions\OrCondition;
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\PostalCityTileField;
use con4gis\FrameworkBundle\Classes\TileFields\StreetTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TagTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TextTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TileField;
use con4gis\FrameworkBundle\Classes\TileFields\WrapperTileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\Database;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\DataModelBundle\Classes\ShowcaseResultConverter;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildTypeModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\ServerService;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WishlistModuleController extends AbstractFrontendModuleController
{
    const TYPE = 'wishlist_module';
    
    private $model = null;
    private OfferLoaderService $offerLoaderService;
    private ServerService $serverService;

    public function __construct(OfferLoaderService $offerLoaderService, ServerService $serverService)
    {
        $this->offerLoaderService = $offerLoaderService;
        $this->serverService = $serverService;
    }
    
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->model = $model;
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js", ResourceLoader::JAVASCRIPT, "c4g-framework");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing_wishlist.min.css");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js|async", ResourceLoader::JAVASCRIPT, "c4g-all");
        System::loadLanguageFile('offer_list');
        System::loadLanguageFile("tl_gutesio_mini_wishlist");
        $list = $this->getList();
        $fields = $this->getListFields();
        $clientUuid = $this->checkCookieForClientUuid($request);
        $data = $this->getListData($clientUuid);
        if (count($data) > 0 && is_array($data) && !($data[0])) {
            $data = [$data];
        }
        
        $fc = new FrontendConfiguration('entrypoint_'.$this->model->id);
        $fc->addTileList($list, $fields, $data);
        $jsonConf = json_encode($fc);
        if ($jsonConf === false) {
            // error encoding
            C4gLogModel::addLogEntry("operator", json_last_error_msg());
            $template->configuration = [];
        } else {
            $template->setData($data);
            $template->configuration = $jsonConf;
        }

        $template->entrypoint = 'entrypoint_'.$this->model->id;
        $response = $template->getResponse();
        $response = $this->addCookieToResponse($response, $request, $clientUuid);
        $response->setClientTtl(0);
        
        return $response;
    }
    
    /**
     * @Route("/gutesio/operator/wishlist/add/{type}/{uuid}", name="add_to_wishlist", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function addToWishlist(Request $request, $type, $uuid)
    {
        $this->get('contao.framework')->initialize();
        $clientUuid = $this->checkCookieForClientUuid($request);
        $table = $type === "showcase" ? "tl_gutesio_data_element" : "tl_gutesio_data_child";
        $db = Database::getInstance();
        if (strpos($uuid, "{") === false) {
            $uuid = "{" . $uuid . "}";
        }
        // check if item is already on wishlist
        $count = $db->prepare("SELECT * FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ? AND `dataUuid` = ?")
            ->execute($clientUuid, $uuid)->count();
        if ($count > 0 ) {
            $response = new JsonResponse(['onList'=> true], 400);
        } else {
            $sql = "INSERT INTO tl_gutesio_data_wishlist %s";
            $insertData = [
                'tstamp' => time(),
                'uuid' => C4GUtils::getGUID(),
                'clientUuid' => $clientUuid,
                'dataUuid' => $uuid,
                'dataTable' => $table
            ];
            try {
                $db->prepare($sql)->set($insertData)->execute();
                $response = new JsonResponse(["updatedData" => ["on_wishlist" => "1", "not_on_wishlist" => "0"], "updateType" => "single"]);
            } catch(\Exception $exception) {
                $response = new JsonResponse(["dbgMessage" => $exception->getMessage()], 500);
            }
        }
        // save cookie for 30 days
        $response = $this->addCookieToResponse($response, $request, $clientUuid);

        return $response;
    }
    
    /**
     * @Route("/gutesio/operator/wishlist/remove/{uuid}", name="remove_from_wishlist", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function removeFromWishlist(Request $request, $uuid)
    {
        $this->get('contao.framework')->initialize();
        $clientUuid = $this->checkCookieForClientUuid($request);
        if (strpos($uuid, "{") === false) {
            $uuid = "{" . $uuid . "}";
        }
        $table = $type === "showcase" ? "tl_gutesio_data_element" : "tl_gutesio_data_child";
        $db = Database::getInstance();
        $sql = "DELETE FROM tl_gutesio_data_wishlist WHERE `dataUuid` = ? AND `clientUuid` = ?";
        try {
            $result = $db->prepare($sql)->execute($uuid, $clientUuid);
            if ($result->affectedRows > 0) {
                $data = [
                    'not_on_wishlist' => "1",
                    'on_wishlist' => "0"
                ];
                $response = new JsonResponse(['updatedData' => $data, 'updateType' => "single"]);
            } else {
                // no data deleted -> no data present
                $response = new JsonResponse([], 400);
            }
        } catch(\Exception $exception) {
            $response = new JsonResponse([], 500);
        }
        // save cookie for 30 days
        $response = $this->addCookieToResponse($response, $request, $clientUuid);
        
        return $response;
    }
    
    /**
     * @Route("/gutesio/operator/wishlist/removeWithResult/{uuid}", name="remove_with_result_from_wishlist", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function removeWithResultFromWishlist(Request $request, $uuid)
    {
        $this->get('contao.framework')->initialize();
        $clientUuid = $this->checkCookieForClientUuid($request);
        if (strpos($uuid, "{") === false) {
            $uuid = "{" . $uuid . "}";
        }
        $table = $type === "showcase" ? "tl_gutesio_data_element" : "tl_gutesio_data_child";
        $db = Database::getInstance();
        $sql = "DELETE FROM tl_gutesio_data_wishlist WHERE `dataUuid` = ? AND `clientUuid` = ?";
        try {
            $result = $db->prepare($sql)->execute($uuid, $clientUuid);
            if ($result->affectedRows > 0) {
                $data = $this->getListData($clientUuid);
                if (is_array($data) && count($data) > 0 && !($data[0])) {
                    $data = [$data];
                }
                foreach ($data as $key => $datum) {
                    $typeString = "";
                    $types = $datum['types'];
                    foreach ($types as $type) {
                        $typeString .= $type['label'] . ",";
                    }
                    $datum['types'] = $typeString;
                    $data[$key] = $datum;
                }
                $response = new JsonResponse(['updatedData' => $data, 'updateType' => "all"]);
            } else {
                // no data deleted -> no data present
                $response = new JsonResponse([], 404);
            }
        } catch(\Exception $exception) {
            $response = new JsonResponse([], 500);
        }
        // save cookie for 30 days
        $response = $this->addCookieToResponse($response, $request, $clientUuid);
        
        return $response;
    }
    
    /**
     * @Route("/gutesio/operator/wishlist/getItemCount", name="get_wishlist_item_count", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function getItemCount(Request $request)
    {
        $this->get('contao.framework')->initialize();
        $clientUuid = $this->checkCookieForClientUuid($request);
        $db = Database::getInstance();
        $sql = "SELECT * FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ?";
        $count = $db->prepare($sql)->execute($clientUuid)->count();
        
        $response = new JsonResponse(['count' => $count]);
        // save cookie for 30 days
        $response = $this->addCookieToResponse($response, $request, $clientUuid);
        
        return $response;
    }
    
    /**
     * @Route("/gutesio/operator/wishlist/clearItems", name="clear_wishlist_items", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function clearWishList(Request $request)
    {
        $this->get('contao.framework')->initialize();
        $clientUuid = $this->checkCookieForClientUuid($request);
        $db = Database::getInstance();
        $deletedItems = $db->prepare("DELETE FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ?")
            ->execute($clientUuid)->affectedRows;
        
        $response = new JsonResponse([
            'count' => $deletedItems,
            'success' => true
        ]);
    
        // save cookie for 30 days
        $response = $this->addCookieToResponse($response, $request, $clientUuid);
        
        return $response;
    }
    
    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request->cookies->get('clientUuid');
        if ($clientUuidCookie === null) {
            $clientUuid = C4GUtils::getGUID();
            return $clientUuid;
        } else {
            return $clientUuidCookie;
        }
    }
    
    private function getList()
    {
        $tileList = new TileList();
        $tileList->setClassName("wishlist");
        $tileList->setTileClassName("item");
        $tileList->setLayoutType("list");
        $headline = StringUtil::deserialize($this->model->headline);
        $tileList->setHeadline($headline['value'] ?: '');
        $tileList->setHeadlineLevel((int) str_replace("h", "", $headline['unit']) ?: 1);
        $tileList->setTextAfterUpdate($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['textBeforeUpdate']);
        $tileList->setTextBeforeUpdate($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['textAfterUpdate']);
    
        return $tileList;
    }
    
    private function getListFields()
    {
        $fields = [];
    
        $field = new ImageTileField();
        $field->setName("imageList");
        $field->setWrapperClass('c4g-list-element__image-wrapper');
        $field->setClass('c4g-list-element__image');
        $fields[] = $field;
    
        $field = new HeadlineTileField();
        $field->setName("name");
        $field->setWrapperClass('c4g-list-element__title-wrapper');
        $field->setClass('c4g-list-element__title');
        $field->setLevel(3);
        $fields[] = $field;
    
        $field = new TextTileField();
        $field->setName("types");
        $field->setWrapperClass('c4g-list-element__types-wrapper');
        $field->setClass('c4g-list-element__types');
        $fields[] = $field;

        //ToDo weitere Daten
        if ($this->model->gutesio_show_contact_data) {
//            $field = new StreetTileField();
//            $field->setName("locationStreet");
//            $field->setWrapperClass("c4g-list-element__street-wrapper");
//            $field->setClass("c4g-list-element__street");
//            $fields[] = $field;
//
//            $field = new PostalCityTileField();
//            $field->setName("locationCity");
//            $field->setWrapperClass("c4g-list-element__city-wrapper");
//            $field->setClass("c4g-list-element__city");
//            $fields[] = $field;
        }

        $field = new TagTileField();
        $field->setName("tags");
        $field->setWrapperClass("c4g-list-element__tags-wrapper");
        $field->setClass("c4g-list-element__tag");
        $field->setInnerClass("c4g-list-element__tag-image");
        $field->setLinkField("linkHref");
        $fields[] = $field;
    
        $field = new TextTileField();
        $field->setName("vendor");
        $field->setWrapperClass('c4g-list-element__elementname-wrapper');
        $field->setClass('c4g-list-element__elementname');
        $fields[] = $field;
        
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $showcaseUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->showcaseDetailPage."}}");
        $productUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->productDetailPage."}}");
        $eventUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->eventDetailPage."}}");
        $jobUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->jobDetailPage."}}");
        $serviceUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->serviceDetailPage."}}");
        $arrangementUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->arrangementDetailPage."}}");
        $voucherUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->voucherDetailPage."}}");
    
        $urlSuffix = Config::get('urlSuffix');
        
        $field = new WrapperTileField();
        $field->setWrappedFields(['cart-link', 'uuid', 'alias', 'uuid']);
        $field->setClass("c4g-list-element__buttons-wrapper");
        $fields[] = $field;
        
        $showcaseUrl = str_replace($urlSuffix, "", $showcaseUrl);

        $user = FrontendUser::getInstance();
        $offerCondition = new OrCondition();
        $offerCondition->addConditions(
            new FieldValueCondition('internal_type', 'product'),
            new FieldValueCondition('internal_type', 'voucher')
        );

        $field = new LinkButtonTileField();
        $field->setName("uuid");
        $field->setHrefField("uuid");
        $field->setWrapperClass("c4g-list-element__cart-wrapper");
        $field->setClass("c4g-list-element__cart-link put-in-cart");
        $field->setHref($this->serverService->getMainServerURL()."/gutesio/main/cart/add");
        $field->setLinkText($GLOBALS['TL_LANG']['offer_list']['frontend']['putInCart']);
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->addConditionalClass("in_cart", "in-cart");
        $field->setAsyncCall(true);
        $field->addCondition($offerCondition);
        $field->addCondition(new FieldValueCondition('offerForSale', '1'));
        $field->addCondition(new FieldNotValueCondition('rawPrice', ''));
        $field->addCondition(new FieldNotValueCondition('rawPrice', '0'));
        $field->addCondition(new FieldNotValueCondition('priceStartingAt', '1'));
        $field->addCondition(new FieldNotValueCondition('availableAmount', '0'));
        $field->addCondition(new FieldNotValueCondition('ownerMemberId', (string) $user->id));
        $field->setAddDataAttributes(true);
        $field->setHookAfterClick(true);
        $field->setHookName("addToCart");
        $page = $this->model->cart_page ?: 0;
        if ($page !== 0) {
            $page = PageModel::findByPk($page);
            if ($page) {
                $field->setRedirectPageOnSuccess($page->getAbsoluteUrl());
            }
        }
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName("uuid");
        $field->setWrapperClass("c4g-list-element__cart-wrapper");
        $field->setClass("c4g-list-element__cart-link not-available");
        $field->setFormat('Zurzeit nicht verfügbar');
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->addCondition($offerCondition);
        $field->addCondition(new FieldValueCondition('offerForSale', '1'));
        $field->addCondition(new FieldNotValueCondition('rawPrice', ''));
        $field->addCondition(new FieldNotValueCondition('rawPrice', '0'));
        $field->addCondition(new FieldNotValueCondition('priceStartingAt', '1'));
        $field->addCondition(new FieldValueCondition('availableAmount', '0'));
        $field->addCondition(new FieldNotValueCondition('ownerMemberId', (string) $user->id));
        $fields[] = $field;

        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref("$showcaseUrl/alias" . $urlSuffix);
        $field->setHrefField("alias");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("showcase");
        $fields[] = $field;
    
        $productUrl = str_replace($urlSuffix, "", $productUrl);
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref($productUrl."/uuid" . $urlSuffix);
        $field->setHrefFields(["uuid"]);
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("product");
        //$field->setExternalLinkField("external_link");
        $fields[] = $field;
    
        $eventUrl = str_replace($urlSuffix, "", $eventUrl);
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref($eventUrl."/uuid" . $urlSuffix);
        $field->setHrefField("uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("event");
        //$field->setExternalLinkField("external_link");
        $fields[] = $field;
    
        $jobUrl = str_replace($urlSuffix, "", $jobUrl);
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref($jobUrl."/uuid" . $urlSuffix);
        $field->setHrefField("uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("job");
        //$field->setExternalLinkField("external_link");
        $fields[] = $field;
    
        $arrangementUrl = str_replace($urlSuffix, "", $arrangementUrl);
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref($arrangementUrl."/uuid" . $urlSuffix);
        $field->setHrefField("uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("arrangement");
        //$field->setExternalLinkField("external_link");
        $fields[] = $field;

        $serviceUrl = str_replace($urlSuffix, "", $serviceUrl);
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref($serviceUrl."/uuid" . $urlSuffix);
        $field->setHrefField("uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("service");
        //$field->setExternalLinkField("external_link");
        $fields[] = $field;

        $voucherUrl = str_replace($urlSuffix, "", $voucherUrl);
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref($voucherUrl."/uuid" . $urlSuffix);
        $field->setHrefField("uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("voucher");
        //$field->setExternalLinkField("external_link");
        $fields[] = $field;
        
        $field = new LinkButtonTileField();
        $field->setName("uuid");
        $field->setWrapperClass('c4g-list-element__delete-wrapper');
        $field->setClass('c4g-list-element__delete-link js-deleteFromGlobalList');
        $field->setHrefField("uuid");
        $field->setHref("/gutesio/operator/wishlist/removeWithResult/uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['delete']);
        $field->setAsyncCall(true);
        $fields[] = $field;

        return $fields;
    }
    
    private function getListData($clientUuid)
    {
        $db = Database::getInstance();
        $converter = new ShowcaseResultConverter();
        $sql = "SELECT * FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ?";
        $arrWishlistElements = $db->prepare($sql)->execute($clientUuid)->fetchAllAssoc();
        $arrShowcaseElements = [];
        $arrOfferElements = [];
        foreach ($arrWishlistElements as $element) {
            $table = $element['dataTable'];
            $uuid = $element['dataUuid'];
            $sql = "SELECT * FROM $table WHERE `uuid` = ?";
            $dataEntry = $db->prepare($sql)->execute($uuid)->fetchAssoc();
            if ($dataEntry) {
                unset($dataEntry['logo']);
                unset($dataEntry['imageGallery']);
                if ($table === "tl_gutesio_data_element") {
                    $dataEntry['internal_type'] = "showcase";
                    $arrShowcaseElements[] = $dataEntry;
                } else {
                    $typeId = $dataEntry['typeId'];
                    $sql = "SELECT * FROM tl_gutesio_data_child_type WHERE `uuid` = ?";
                    $arrType = $db->prepare($sql)->execute($typeId)->fetchAssoc();
                    if ($arrType) {
                        $dataEntry['internal_type'] = $arrType['type'];
                        $dataEntry['type'] = $arrType['type'];
                        $dataEntry = $this->offerLoaderService->getAdditionalData([$dataEntry])[0];
                        unset($dataEntry['type']);
                        $arrOfferElements[] = $dataEntry;
                    } else {
                        $sql2 = "DELETE FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ? AND `dataTable` = ? AND  `dataUuid` = ?";
                        $db->prepare($sql2)->execute($clientUuid, $table, $uuid);
                    }
                }
            } else {
                $sql2 = "DELETE FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ? AND `dataTable` = ? AND  `dataUuid` = ?";
                $db->prepare($sql2)->execute($clientUuid, $table, $uuid);
            }
        }
        $arrResult = $converter->convertDbResult($arrShowcaseElements, ['loadTagsComplete' => true]);
        if (count($arrResult) > 0 && !$arrResult[0]) {
            $arrResult = [$arrResult];
        }
        foreach ($arrResult as $key => $showcase) {
            foreach ($showcase['types'] as $type) {
                $types[] = $type['label'];
                $arrResult[$key]['types'] = implode(', ', $types);
            }
        }
        $arrOffers = [];
        foreach ($arrOfferElements as $element) {
            $offer = [];
            if (C4GUtils::isBinary($element['imageOffer'])) {
                $model = FilesModel::findByUuid(StringUtil::binToUuid($element['imageOffer']));
                if ($model) {
                    $offer['imageList'] = $converter->createFileDataFromModel($model);
                }
            } else if ($element['imageOffer']) {
                $model = FilesModel::findByUuid($element['imageOffer']);
                if ($model) {
                    $offer['imageList'] = $converter->createFileDataFromModel($model);
                }
            }
            $vendorUuid = $db->prepare("SELECT * FROM tl_gutesio_data_child_connection WHERE `childId` = ? LIMIT 1")
                ->execute($element['uuid'])->fetchAssoc();
            
            $vendor = $db->prepare("SELECT * FROM tl_gutesio_data_element WHERE `uuid` = ?")
                ->execute($vendorUuid['elementId'])->fetchAssoc();
            $offer['vendor'] = html_entity_decode($vendor['name']);
            $offer['name'] = html_entity_decode($element['name']);

            //hotfix special char
            $offer['vendor'] = str_replace('&#39;', "'", $offer["vendor"]);
            $offer['name'] = str_replace('&#39;', "'", $offer["name"]);

            $offer['internal_type'] = $element['internal_type'];
            $offer['uuid'] = strtolower(str_replace(['{', '}'], '', $element['uuid']));
            $offer['elementId'] = strtolower(str_replace(['{', '}'], '', $vendor['uuid']));
            $type = GutesioDataChildTypeModel::findBy("uuid", $element['typeId'])->fetchAll()[0];
            $offer['types'] = $type['name'];
            $offer['alias'] = $element['alias'];
            if ($element['foreignLink'] && $element['directLink']) {
                $offer['external_link'] = $element['foreignLink'];
            }

            foreach ($element as $key => $item) {
                if (!array_key_exists($key, $offer)) {
                    $offer[$key] = $item;
                }
            }
            $arrOffers[] = $offer;
        }
        
        $arrResult = array_merge($arrResult, $arrOffers);
        return $this->recursivelyConvertToUtf8($arrResult);
    }

    private function recursivelyConvertToUtf8($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->recursivelyConvertToUtf8($value);
            }
        } elseif (is_string($data)) {
            return mb_convert_encoding($data, "UTF-8", "UTF-8");
        }
        return $data;
    }
    
    private function addCookieToResponse(Response $response, Request $request, string $clientUuid)
    {
        // save cookie for 30 days
        $response->headers->setCookie(
            new Cookie(
                "clientUuid",
                $clientUuid,
                new \DateTime("+30 days"),
                "/",
                $request->getHost(),
                false,
                true,
                false,
                "lax"
            )
        );
        
        return $response;
    }
}