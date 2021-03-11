<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Controller;


use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\ResourceLoader;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ListTypeTileFields\ListTypeModalButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ModalButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TagTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TextTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TileField;
use con4gis\FrameworkBundle\Classes\TileFields\WrapperTileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use con4gis\FrameworkBundle\Classes\Utility\RegularExpression;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\Database;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\DataModelBundle\Classes\ShowcaseResultConverter;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildTypeModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\ShowcaseService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WishlistModuleController extends AbstractFrontendModuleController
{
    const TYPE = 'wishlist_module';
    const CC_FORM_SUBMIT_URL = '/gutesio/main/showcase_child_cc_form_submit';
    
    private $model = null;
    
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->model = $model;
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js?v=" . time(), ResourceLoader::BODY, "c4g-framework");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/css/c4g_listing_wishlist.css");
        ResourceLoader::loadCssResource("/bundles/con4gisframework/css/modal.css");
        System::loadLanguageFile("tl_gutesio_mini_wishlist");
        $list = $this->getList();
        $fields = $this->getListFields();
        $clientUuid = $this->checkCookieForClientUuid($request);
        $data = $this->getListData($clientUuid);
        if (count($data) > 0 && is_array($data) && !($data[0])) {
            $data = [$data];
        }
        foreach ($data as $key => $datum) {
            if (!($datum['internal_type'] === "showcase")) {
                continue;
            }
            $typeString = "";
            $types = $datum['types'];
            foreach ($types as $innerKey => $type) {
                $typeString .= $type['label'];
                if (!($innerKey === array_key_last($types))) {
                    $typeString .= ",";
                }
            }
            $datum['types'] = $typeString;
            $data[$key] = $datum;
        }
        
        $fc = new FrontendConfiguration('entrypoint_'.$this->model->id);
        $fc->addTileList($list, $fields, $data);
        $jsonConf = json_encode($fc);
        if ($jsonConf === false) {
            // error encoding
            C4gLogModel::addLogEntry("operator", json_last_error_msg());
            $template->configuration = [];
        } else {
            /**
             * ajo: Daten ins Template schreiben.
             */
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
        $tileList->setHeadline($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['headline']);
        $tileList->setTextAfterUpdate($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['textBeforeUpdate']);
        $tileList->setTextBeforeUpdate($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['textAfterUpdate']);
    
        return $tileList;
    }
    
    private function getListFields()
    {
        System::loadLanguageFile('tl_gutesio_data_child');
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
        $arrangementUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->arrangementDetailPage."}}");
        
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref("$showcaseUrl/alias");
        $field->setHrefField("alias");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("showcase");
        $fields[] = $field;
    
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref("$productUrl/uuid");
        $field->setHrefField("uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("product");
        $field->setExternalLinkField("external_link");
        $fields[] = $field;
    
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref("$eventUrl/uuid");
        $field->setHrefField("uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("event");
        $field->setExternalLinkField("external_link");
        $fields[] = $field;
    
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref("$jobUrl/uuid");
        $field->setHrefField("uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("job");
        $field->setExternalLinkField("external_link");
        $fields[] = $field;
    
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass('c4g-list-element__more-wrapper');
        $field->setClass('c4g-list-element__more-link');
        $field->setHref("$arrangementUrl/uuid");
        $field->setHrefField("uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("arrangement");
        $field->setExternalLinkField("external_link");
        $fields[] = $field;
        
        $field = new LinkButtonTileField();
        $field->setName("uuid");
        $field->setWrapperClass('c4g-list-element__delete-wrapper');
        $field->setClass('c4g-list-element__delete-link js-deleteFromGlobalList');
        $field->setHrefField("uuid");
        $field->setHref("/gutesio/operator/wishlist/removeWithResult/uuid");
        $field->setLinkText("Löschen");
        $field->setAsyncCall(true);
        $fields[] = $field;

        $field = new ModalButtonTileField();
        $field->setName('cc');
        $field->setWrapperClass('c4g-list-element__clickcollect-wrapper');
        $field->setClass('c4g-list-element__clickcollect');
        $field->setLabel($GLOBALS['TL_LANG']['tl_gutesio_data_child']['frontend']['cc_form']['modal_button_label']);
        $field->setUrl('/gutesio/operator/showcase_child_cc_form/uuid');
        $field->setUrlField('uuid');
        $field->setConfirmButtonText($GLOBALS['TL_LANG']['tl_gutesio_data_child']['frontend']['cc_form']['confirm_button_text']);
        $field->setCloseButtonText($GLOBALS['TL_LANG']['tl_gutesio_data_child']['frontend']['cc_form']['close_button_text']);
        $field->setSubmitUrl(self::CC_FORM_SUBMIT_URL);
        $field->setCondition('clickCollect', '1');
        $field->setCondition('internal_type', 'product');
        $field->setCondition('internal_type', 'showcase', true);
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
        $arrOffers = [];
        foreach ($arrOfferElements as $element) {
            $offer = [];
            $model = FilesModel::findByUuid(StringUtil::binToUuid($element['imageOffer']));
            if ($model) {
                $offer['imageList'] = $converter->createFileDataFromModel($model);
            }
            $vendorUuid = $db->prepare("SELECT * FROM tl_gutesio_data_child_connection WHERE `childId` = ? LIMIT 1")
                ->execute($element['uuid'])->fetchAssoc();
            
            $vendor = $db->prepare("SELECT * FROM tl_gutesio_data_element WHERE `uuid` = ?")
                ->execute($vendorUuid['elementId'])->fetchAssoc();
            $offer['vendor'] = $vendor['name'];
            $offer['name'] = $element['name'];
            $offer['internal_type'] = $element['internal_type'];
            // offer detail page with uuid as alias and without {}?
            $offer['uuid'] = str_replace(["{", "}"], ["", ""], $element['uuid']);
            $type = GutesioDataChildTypeModel::findBy("uuid", $element['typeId'])->fetchAll()[0];
            $offer['types'] = $type['name'];
            $offer['alias'] = $element['alias'];
            if ($element['foreignLink'] && $element['directLink']) {
                $offer['external_link'] = $element['foreignLink'];
            }
            $offer['clickCollect'] = $vendor['clickCollect'];
            
            $arrOffers[] = $offer;
        }
        
        $arrResult = array_merge($arrResult, $arrOffers);
        
        return $arrResult;
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