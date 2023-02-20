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
use con4gis\FrameworkBundle\Classes\Conditions\FieldNotValueCondition;
use con4gis\FrameworkBundle\Classes\Conditions\FieldValueCondition;
use con4gis\FrameworkBundle\Classes\Conditions\OrCondition;
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\AddressTileField;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\OSMOpeningHoursTileField;
use con4gis\FrameworkBundle\Classes\TileFields\PhoneTileField;
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
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use gutesio\OperatorBundle\Classes\Services\ServerService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BannerModuleController extends AbstractFrontendModuleController
{
    private ?ModuleModel $model = null;
    private OfferLoaderService $offerLoaderService;
    private ServerService $serverService;

    public const TYPE = 'banner_module';

    public function __construct(OfferLoaderService $offerLoaderService, ServerService $serverService)
    {
        $this->offerLoaderService = $offerLoaderService;
        $this->serverService = $serverService;
    }
    
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->model = $model;
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js", ResourceLoader::JAVASCRIPT, "c4g-framework");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing_banner.min.css");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js|async", ResourceLoader::JAVASCRIPT, "c4g-all");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/openinghours.js|async", ResourceLoader::JAVASCRIPT, "openinghours");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/phonehours.js|async", ResourceLoader::JAVASCRIPT, "phonehours");

        System::loadLanguageFile('offer_list');
        //System::loadLanguageFile("tl_gutesio_banner");
        $list = $this->getList();
        $fields = $this->getListFields();


        $data = $this->getListData();
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

        return $response;
    }
    
    private function getList()
    {
        $tileList = new TileList();
        $tileList->setClassName("bannerlist");
        $tileList->setTileClassName("item");
        $tileList->setLayoutType("list");
        $headline = StringUtil::deserialize($this->model->headline);
        $tileList->setHeadline($headline['value'] ?: '');
        $tileList->setHeadlineLevel((int) str_replace("h", "", $headline['unit']) ?: 1);
    
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

        //ToDo weitere Daten
        if ($this->model->gutesio_show_contact_data) {
            $field = new AddressTileField();
            $field->setName("address");
            $field->setStreetName('contactStreet');
            $field->setStreetNumberName('contactStreetNumber');
            $field->setPostalName('contactZip');
            $field->setCityName('contactCity');
            $field->setWrapperClass("c4g-list-element__contact-address-wrapper");
            $field->setClass("c4g-list-element__address");
            $field->setConditionField(['contactable']);
            $field->setConditionValue(["1"]);
            $fields[] = $field;

            $field = new AddressTileField();
            $field->setName("address");
            $field->setStreetName('locationStreet');
            $field->setStreetNumberName('locationStreetNumber');
            $field->setPostalName('locationZip');
            $field->setCityName('locationCity');
            $field->setWrapperClass("c4g-list-element__contact-address-wrapper");
            $field->setClass("c4g-list-element__address");
            $field->setConditionField(['contactable']);
            $field->setConditionValue(["0"]);
            $fields[] = $field;

            $field = new PhoneTileField();
            $field->setName("contactPhone");
            $field->setWrapperClass("c4g-list-element__contact-phone-wrapper");
            $field->setClass("c4g-list-element__phone");
            $field->setConditionField(['contactable']);
            $field->setConditionValue(["1"]);
            $fields[] = $field;

            $field = new PhoneTileField();
            $field->setName("phone");
            $field->setWrapperClass("c4g-list-element__contact-phone-wrapper");
            $field->setClass("c4g-list-element__phone");
            $field->setConditionField(['contactable']);
            $field->setConditionValue(["0"]);
            $fields[] = $field;

            $field = new OSMOpeningHoursTileField();
            $field->setName("phoneHours");
            $field->setWrapperClass("c4g-list-element__contact-phonehours-wrapper");
            $field->setClass("c4g-list-element__phonehours");
            $fields[] = $field;

        }

        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $showcaseUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->showcaseDetailPage."}}");
        $productUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->productDetailPage."}}");
        $eventUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->eventDetailPage."}}");
        $jobUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->jobDetailPage."}}");
        $serviceUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->serviceDetailPage."}}");
        $arrangementUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->arrangementDetailPage."}}");
        $personUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->personDetailPage."}}");
        $voucherUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->voucherDetailPage."}}");
    
        $urlSuffix = Config::get('urlSuffix');
        
        $field = new WrapperTileField();
        $field->setWrappedFields(['cart-link', 'uuid', 'alias', 'uuid']);
        $field->setClass("c4g-list-element__buttons-wrapper");
        $fields[] = $field;
        
        $showcaseUrl = str_replace($urlSuffix, "", $showcaseUrl);

        return $fields;
    }
    
    private function getListData()
    {
        $db = Database::getInstance();
        $converter = new ShowcaseResultConverter();

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

            if ($vendorUuid['elementId']) {
                $vendor = $db->prepare("SELECT * FROM tl_gutesio_data_element WHERE `uuid` = ?")
                    ->execute($vendorUuid['elementId'])->fetchAssoc();
                $offer['vendor'] = html_entity_decode($vendor['name']);
                $offer['name'] = html_entity_decode($element['name']);

                //ToDo
                if ($this->model->gutesio_show_contact_data) {
                    if ($vendor['contactStreet']) {
                        $offer['contactStreet'] = html_entity_decode($vendor['contactStreet']);
                        $offer['contactStreetNumber'] = html_entity_decode($vendor['contactStreetNumber']);
                        $offer['contactZip'] = html_entity_decode($vendor['contactZip']);
                        $offer['contactCity'] = html_entity_decode($vendor['contactCity']);
                    } else {
                        $offer['contactStreet'] = html_entity_decode($vendor['locationStreet']);
                        $offer['contactStreetNumber'] = html_entity_decode($vendor['locationStreetNumber']);
                        $offer['contactZip'] = html_entity_decode($vendor['locationZip']);
                        $offer['contactCity'] = html_entity_decode($vendor['locationCity']);
                    }
                }

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

                if ($offer) {
                    foreach ($element as $key => $item) {
                        if (!array_key_exists($key, $offer)) {
                            $offer[$key] = $item;
                        }
                    }
                    $arrOffers[] = $offer;
                }
            }
        }

        $arrResult = $arrOffers;//array_merge($arrResult, $arrOffers);
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
}