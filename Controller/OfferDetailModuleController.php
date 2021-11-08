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
use con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailContactField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailFancyboxImageGallery;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailHeadlineField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailHTMLField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailMapLocationField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailModalFormButtonField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailTagField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailTextField;
use con4gis\FrameworkBundle\Classes\DetailFields\PDFDetailField;
use con4gis\FrameworkBundle\Classes\DetailPage\DetailPage;
use con4gis\FrameworkBundle\Classes\DetailPage\DetailPageSection;
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\SearchConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\DistanceField;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ModalButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TagTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TextTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TileField;
use con4gis\FrameworkBundle\Classes\TileFields\WrapperTileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use con4gis\FrameworkBundle\Classes\Utility\FieldUtil;
use con4gis\FrameworkBundle\Traits\AutoItemTrait;
use con4gis\MapsBundle\Classes\MapDataConfigurator;
use con4gis\MapsBundle\Classes\ResourceLoader as MapsResourceLoader;
use Contao\Config;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Database;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\DataModelBundle\Classes\TypeDetailFieldGenerator;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use gutesio\OperatorBundle\Classes\Services\ShowcaseService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OfferDetailModuleController extends AbstractFrontendModuleController
{
    use AutoItemTrait;

    protected $model = null;
    protected $request = null;
    protected $tileItems = [];

    /**
     * @var OfferLoaderService
     */
    private $offerService = null;
    
    /**
     * @var ShowcaseService
     */
    private $showcaseService;

    private $languageRefs = [];

    const CC_FORM_SUBMIT_URL = '/showcase_child_cc_form_submit.php';
    const COOKIE_WISHLIST = "clientUuid";

    /**
     * OfferDetailModuleController constructor.
     * @param OfferLoaderService|null $offerService
     * @param ShowcaseService $showcaseService
     */
    public function __construct(
        ?OfferLoaderService $offerService,
        ShowcaseService $showcaseService
    ) {
        $this->offerService = $offerService;
        $this->showcaseService = $showcaseService;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        global $objPage;
        $this->model = $model;
        $this->offerService->setModel($model);
        $this->setAlias();
        $pageUrl = "";
        $page = PageModel::findByPk($this->model->gutesio_offer_list_page);
        if ($page) {
            $pageUrl = $page->getAbsoluteUrl();
        }
        $this->offerService->setPageUrl($pageUrl);
        $this->offerService->setRequest($request);
        $this->request = $request;
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js", ResourceLoader::JAVASCRIPT, "c4g-framework");
        ResourceLoader::loadJavaScriptResource("/bundles/con4gismaps/build/c4g-maps.js", ResourceLoader::JAVASCRIPT, "c4g-maps");
        $this->setupLanguage();
        ResourceLoader::loadCssResource("/bundles/con4gisframework/dist/css/tiles.min.css");

        if ($this->model->gutesio_data_layoutType !== "plain") {
            ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_detail.min.css");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/openinghours.js|async", ResourceLoader::JAVASCRIPT, "openinghours");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/bootstrap/util.js|async", ResourceLoader::JAVASCRIPT, "boostrap-util");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/bootstrap/modal.js|async", ResourceLoader::JAVASCRIPT, "boostrap-modal");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js|async|static", ResourceLoader::JAVASCRIPT, "c4g-all");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/detailmap.js|async", ResourceLoader::JAVASCRIPT, "detailmap");
            ResourceLoader::loadCssResource("/bundles/gutesiooperator/vendor/fancybox/jquery.fancybox.min.css");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/fancybox/jquery.fancybox.min.js|async");
        }

        if ($this->alias !== "") {
            $data = $this->offerService->getDetailData($this->alias);
            if ($data) {
                $objPage->pageTitle = $data['name'];
                $conf = new FrontendConfiguration('entrypoint_' . $this->model->id);
                $components = $this->getDetailComponents($data, $request);
                if ($data['type'] === "event") {
                    if ($data['locationElementId']) {
                        $elementUuid = $components['elements'][2][0]['uuid'];
                        if ($elementUuid !== $data['locationElementId']) {
                            $locationElementData = $this->getLocationElementData($data['locationElementId'], true);
                            if ($locationElementData) {
                                $locationList = $this->getLocationList();
                                $conf->addTileList(
                                    $locationList,
                                    $this->tileItems,
                                    [$locationElementData]
                                );
                            }
                        }
                    }
                }
                $conf->addTileList(
                    $components['elements'][0],
                    $components['elements'][1],
                    $components['elements'][2]
                );
                $otherChildData = $this->getChildTileData($data, $request);
                if (count($otherChildData) > 0) {
                    $conf->addTileList(
                        $this->getChildTileList(),
                        $this->getChildTileFields(),
                        $otherChildData
                    );
                    $template->hasOtherChilds = true;
                }
                $conf->setLanguage($objPage->language);
                if (!empty($data)) {
                    if ($this->model->gutesio_data_render_searchHtml) {
                        $sc = new SearchConfiguration();
                        $sc->addData($data, ['name', 'description', 'displayType', 'extendedSearchTerms']);
                    }
                } else {
                    throw new RedirectResponseException($pageUrl);
                }
                $template->entrypoint = 'entrypoint_' . $this->model->id;
                $strConf = json_encode($conf);
                $error = json_last_error_msg();
                if ($error && (strtoupper($error) !== "NO ERROR")) {
                    C4gLogModel::addLogEntry("operator", $error);
                }
                $template->configuration = $strConf;
                if ($this->model->gutesio_data_render_searchHtml) {
                    $template->searchHTML = $sc->getHTML();
                }
            } else {
                throw new RedirectResponseException($pageUrl);
            }
        } else {
            throw new RedirectResponseException($pageUrl);
        }
        $template->detailData = $data;
        $template->mapData = $this->getMapData();
        
        return $template->getResponse();
    }

    private function setupLanguage()
    {
        System::loadLanguageFile("offer_list");
        System::loadLanguageFile("operator_showcase_list");
        System::loadLanguageFile("gutesio_frontend");
        $this->languageRefs = $GLOBALS['TL_LANG']['offer_list'];
    }

    private function getDetailComponents(array $data, Request $request)
    {
        $components = [];
//        $components['details'] = [
//            $this->getDetailPage(),
//            $this->getDetailFields($data),
//            $data
//        ];
        $components['elements'] = [
            $this->getElementTileList(),
            $this->getElementFields(),
            $this->getElementData($data, $request)
        ];

        return $components;
    }


    protected function getDetailPage()
    {
        $page = new DetailPage();
        $page->setHeadline($GLOBALS['TL_LANG']['offer_list']['frontend']['details']['headline']);
        
        $page->setSections($this->getSections());
        $page->setShowAnchorMenu(true);
        $page->setMenuSectionIndex(2);
        return $page;
    }
    
    protected function getMapData()
    {
        $settings = GutesioOperatorSettingsModel::findSettings();
        $mapData = MapDataConfigurator::prepareMapData(
            ContentModel::findById($settings->detail_map),
            Database::getInstance(),
            ["profile" => $settings->detail_profile],
            false
        );
        MapsResourceLoader::loadResources(["router" => true], $mapData);
    
        $mapData['geopicker']['input_geo_x'] = "#geox";
        $mapData['geopicker']['input_geo_y'] = "#geoy";
        $mapData['addIdToDiv'] = false;
        
        return $mapData;
    }

    private function getSections()
    {
        $sections = [];
        $section = new DetailPageSection('', false, "detail-view__section-headline", false);
        $sections[] = $section;

        $section = new DetailPageSection('', true, "detail-view__section-two", false);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['description'], true, "detail-view__section-description", true);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['detailData'], true, "detail-view__section-detaildata", true);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['tags'], true, "detail-view__section-tags", false);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['contact'], true, "detail-view__section-contact", true);
        $sections[] = $section;

        return $sections;
    }

    protected function getDetailFields(array $data)
    {
        $settings = C4gSettingsModel::findSettings();
        $fields = [];

        $field = new DetailHeadlineField();
        $field->setName('name')
            ->setClass("detail-view__headline")
            ->setLevel(1)
            ->setSection(1);
        $fields[] = $field;

        $field = new DetailHTMLField();
        $field->setName('description');
        $field->setSection(3);
        $field->setClass("detail-view__description");
        $fields[] = $field;

        $field = new DetailFancyboxImageGallery();
        $field->setName("imageGallery");
        $field->setClass("detail-view__image-gallery");
        $field->setSection(3);
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("strikePrice");
        $field->setClass('detail-view__strike-price');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("price");
        $field->setClass('detail-view__price');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("color");
        $field->setLabel($this->languageRefs['color'][0]);
        $field->setClass('detail-view__color');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("size");
        $field->setLabel($this->languageRefs['size'][0]);
        $field->setClass('detail-view__size');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("beginDate");
        $field->setClass('detail-view__begin-date');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("beginTime");
        $field->setClass('detail-view__begin-time');
        $fields[] = $field;
    
        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("nextDate");
        $field->setLabel($this->languageRefs['nextDate'][0]);
        $field->setClass('detail-view__next-date');
        $fields[] = $field;
    
        $field = new DetailTextField();
        $field->setSection(4);
        $field->setLabel($this->languageRefs['location']);
        $field->setName("locationElementName");
        $field->setClass('detail-view__location-element-name');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("appointmentUponAgreement");
        $field->setClass('detail-view__appointment-upon-agreement');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("minCredit");
        $field->setClass('detail-view__min-credit');
        $field->setLabel($this->languageRefs['minCredit']);
        $field->setFormat('%s €');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("maxCredit");
        $field->setClass('detail-view__max-credit');
        $field->setLabel($this->languageRefs['maxCredit']);
        $field->setFormat('%s €');
        $fields[] = $field;

        global $objPage;
        $field = new DetailModalFormButtonField();
        $field->setSection(4);
        $field->setName('cc');
        $field->setClass('cc detail-view__modal');
        $field->setLabel($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['modal_button_label']);
        $field->setUrl('/gutesio/operator/showcase_child_cc_form/'.$objPage->language.'/uuid');
        $field->setUrlField('uuid');
        $field->setConfirmButtonText($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['confirm_button_text']);
        $field->setCloseButtonText($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['close_button_text']);
        $field->setSubmitUrl(rtrim($settings->con4gisIoUrl, '/').self::CC_FORM_SUBMIT_URL);
        $field->setConditionField('clickCollect');
        $field->setConditionField('type');
        $field->setConditionValue('1');
        $field->setConditionValue('product');
        $field->setInnerFields([
            'name',
            'imageGallery',
            'strikePrice',
            'price',
            'beginDate',
            'beginTime',
        ]);
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setName("displayType");
        $field->setClass("displayType detail-view__display-type");
        $field->setSection(5);
        $field->setLabel($this->languageRefs['displayType']);
        $fields[] = $field;

        $field = new DetailTagField();
        $field->setSection(5);
        $field->setName("tags");
        $field->setClass('detail-view__tags');
        $fields[] = $field;
        
        $typeFields = $this->getTypeFields($data['typeId']);
        foreach ($typeFields as $key => $typeField) {
            $typeField->setSection(5);
            $typeFields[$key] = $typeField;
        }
        $fields = array_merge($fields, $typeFields);

        $field = new DetailTextField();
        $field->setSection(5);
        $field->setName('taxNote');
        $field->setClass('taxNote detail-view__taxnote');
        $fields[] = $field;

        $field = new PDFDetailField();
        $field->setName("infoFile");
        $field->setLabel($this->languageRefs['infoFile_label']);
        $field->setTitle($this->languageRefs['infoFile_title']);
        $field->setClass("infoFile detail-view__infofile");
        $field->setSection(5);
        $fields[] = $field;

        $contactField = new DetailContactField();
        $contactField->setSection(6);
        $contactField->setLabel($GLOBALS['TL_LANG']['offer_list']['frontend']['details']['contact']);
        $contactField->setEmailFieldName('email');
        $contactField->setPhoneFieldName('phone');
        $contactField->setWebsiteFieldName('website');
        $contactField->setWebsiteTextFieldName("websiteLabel");
        $contactField->setOpeningTimesFieldName("opening_hours");
        $contactField->setAddressFieldnamePrefix("contact");
        $contactField->setAddressFieldnameFallbackPrefix("location");
        $contactField->setClass("detail-view__contact-wrapper");
        $contactField->setWithSocialMedia(true);
        $fields[] = $contactField;

        $field = new DetailMapLocationField();
        $field->setSection(6);
        $field->setClass("detail-view__map-wrapper");
        $field->setName('mapLocation');
        $field->setGeoxField('geox');
        $field->setGeoyField('geoy');
        $fields[] = $field;

        return $fields;
    }
    
    private function getTypeFields(string $typeId): array
    {
        $stm = Database::getInstance()
            ->prepare("SELECT * FROM tl_gutesio_data_child_type WHERE `uuid` = ?");
        $arrTypes = $stm->execute($typeId)->fetchAllAssoc();
        $typeFields = [];
        foreach ($arrTypes as $type) {
            if ($type['technicalKey']) {
                $arrTechnicalKeys = StringUtil::deserialize($type['technicalKey'], true);
                if ($arrTechnicalKeys && is_array($arrTechnicalKeys) && count($arrTechnicalKeys) > 0) {
                    $typeFields = array_merge($typeFields, TypeDetailFieldGenerator::getFieldsForTypes($arrTechnicalKeys));
                }
            }
        }
        
        
        return FieldUtil::makeFieldArrayUnique($typeFields);
    }

    protected function getElementTileList(): TileList
    {
        $this->tileList = new TileList('showcase-tiles');
        $this->tileList->setHeadline($this->languageRefs['offeredBy']);
        $this->tileList->setHeadlineLevel(2);
        $this->tileList->setClassName("showcase-tiles c4g-list-outer");
        $this->tileList->setLayoutType("list");
        $this->tileList->setTileClassName("showcase-tile");
        return $this->tileList;
    }
    
    private function getLocationList()
    {
        $locationList = new TileList('location-tiles');
        $locationList->setHeadline($this->languageRefs['location']);
        $locationList->setHeadlineLevel(2);
        $locationList->setClassName("showcase-tiles c4g-list-outer");
        $locationList->setLayoutType("list");
        $locationList->setListWrapper(true);
        $locationList->setWrapperId("location-tiles");
        $locationList->setTileClassName("showcase-tile");
        return $locationList;
    }

    protected function getElementFields(): array
    {

        $field = new ImageTileField();
        $field->setName("imageList");
        $field->setRenderSection(TileField::RENDERSECTION_HEADER);
        $field->setWrapperClass("c4g-list-element__image-wrapper");
        $field->setClass("c4g-list-element__image");
        $this->tileItems[] = $field;

        $field = new HeadlineTileField();
        $field->setName("name");
        $field->setWrapperClass("c4g-list-element__title-wrapper");
        $field->setClass("c4g-list-element__title");
        $field->setLevel(4);
        $this->tileItems[] = $field;

        $field = new TextTileField();
        $field->setName("types");
        $field->setWrapperClass("c4g-list-element__types-wrapper");
        $field->setClass("c4g-list-element__types");
        $field->setLabel($GLOBALS['TL_LANG']['operator_showcase_list']['types'][0]);
        $this->tileItems[] = $field;

        $field = new TagTileField();
        $field->setName("tags");
        $field->setWrapperClass("c4g-list-element__tags-wrapper");
        $field->setClass("c4g-list-element__tag");
        $field->setLinkField("linkHref");
        $this->tileItems[] = $field;

        $field = new DistanceField();
        $field->setName("distance");
        $field->setWrapperClass("c4g-list-element__distance-wrapper");
        $field->setClass("c4g-list-element__distance");
        $field->setLabel($GLOBALS['TL_LANG']['operator_showcase_list']['distance'][0]);
        $field->setGeoxField("geox");
        $field->setGeoyField("geoy");
        $this->tileItems[] = $field;
    
        $field = new WrapperTileField();
        $field->setWrappedFields(['uuid', 'alias']);
        $field->setClass("c4g-list-element__buttons-wrapper");
        $this->tileItems[] = $field;
    
        $field = new LinkButtonTileField();
        $field->setName("uuid");
        $field->setHrefField("uuid");
        $field->setWrapperClass("c4g-list-element__notice-wrapper");
        $field->setClass("c4g-list-element__notice-link put-on-wishlist");
        $field->setHref("/gutesio/operator/wishlist/add/showcase/uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['operator_showcase_list']['putOnWishlist']);
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->addConditionalClass("on_wishlist", "on-wishlist");
        $field->setAsyncCall(true);
        $field->setConditionField("not_on_wishlist");
        $field->setConditionValue('1');
        $field->setAddDataAttributes(true);
        $field->setHookAfterClick(true);
        $field->setHookName("addToWishlist");
        $this->tileItems[] = $field;
    
        $field = new LinkButtonTileField();
        $field->setName("uuid");
        $field->setHrefField("uuid");
        $field->setWrapperClass("c4g-list-element__notice-wrapper");
        $field->setClass("c4g-list-element__notice-link remove-from-wishlist");
        $field->setHref("/gutesio/operator/wishlist/remove/uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['operator_showcase_list']['removeFromWishlist']);
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->setAsyncCall(true);
        $field->addConditionalClass("on_wishlist", "on-wishlist");
        $field->setConditionField("on_wishlist");
        $field->setConditionValue('1');
        $field->setAddDataAttributes(true);
        $field->setHookAfterClick(true);
        $field->setHookName("removeFromWishlist");
        $this->tileItems[] = $field;

        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $url = Controller::replaceInsertTags("{{link_url::" . $objSettings->showcaseDetailPage . "}}");
        $href = $url . '/alias';
        $urlSuffix = Config::get('urlSuffix');
        $href = str_replace($urlSuffix, "", $href);
        $href .= $urlSuffix;

        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setWrapperClass("c4g-list-element__more-wrapper");
        $field->setClass("c4g-list-element__more-link");
        $field->setHrefField("alias");
        $field->setHref($href);
        $field->setLinkText($GLOBALS['TL_LANG']['operator_showcase_list']['alias_link_text']);
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->setExternalLinkField('foreignLink');
        $field->setExternalLinkFieldConditionField("directLink");
        $field->setExternalLinkFieldConditionValue("1");
        $this->tileItems[] = $field;

        return $this->tileItems;
    }

    private function getElementData(array $data, Request $request): array
    {
        $results = $this->offerService->getElementData($data['uuid']);
        $clientUuid = $this->checkCookieForClientUuid($request);
        $db = Database::getInstance();
        foreach ($results as $key => $row) {
            $types = [];
            if ($clientUuid !== null) {
                $sql = "SELECT * FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ? AND `dataUuid` = ?";
                $result = $db->prepare($sql)->execute($clientUuid, $row['uuid'])->fetchAssoc();
                if ($result) {
                    $row['on_wishlist'] = "1";
                } else {
                    $row['not_on_wishlist'] = "1";
                }
            }
            foreach ($row['types'] as $type) {
                $types[] = $type['label'];
                $row['types'] = implode(', ', $types);
            }
            $results[$key] = $row;
        }

        return $results;
    }

    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request->cookies->get(self::COOKIE_WISHLIST);
        return $clientUuidCookie === null ? null : $clientUuidCookie;
    }
    
    /**
     * Methods for creating the "other offers from the vendor" list
     */
    
    private function getChildTileList()
    {
        $tileList = new TileList();
        $tileList->setHeadline($GLOBALS['TL_LANG']["offer_list"]['otherOffers']);
        $tileList->setLayoutType('list');
        $tileList->setClassName("offer-tiles c4g-list-outer");
        $tileList->setListWrapper(true);
        $tileList->setWrapperId("offer-tiles");
        $tileList->setHeadlineLevel(2);
        $tileList->setWithTextFilter(true);
        $tileList->setTextFilterFields(['name', 'shortDescription', 'typeName']);
        
        return $tileList;
    }
    
    private function getChildTileFields()
    {
        $fields = [];
        
        $field = new ImageTileField();
        $field->setWrapperClass("c4g-list-element__image-wrapper");
        $field->setClass("c4g-list-element__image");
        $field->setName('image');
        $fields[] = $field;
        
        $field = new HeadlineTileField();
        $field->setName('name');
        $field->setLevel(4);
        $field->setWrapperClass("c4g-list-element__title-wrapper");
        $field->setClass("c4g-list-element__title");
        $fields[] = $field;
        
        $field = new TextTileField();
        $field->setName('typeName');
        $field->setWrapperClass("c4g-list-element__typename-wrapper");
        $field->setClass("c4g-list-element__typename");
        $fields[] = $field;
        
        $field = new TextTileField();
        $field->setName('shortDescription');
        $field->setWrapperClass("c4g-list-element__shortdescription-wrapper");
        $field->setClass("c4g-list-element__shortdescription");
        $fields[] = $field;
        
        $field = new TextTileField();
        $field->setName('strikePrice');
        $field->setWrapperClass("c4g-list-element__strikeprice-wrapper");
        $field->setClass("c4g-list-element__strikeprice");
        $fields[] = $field;
        
        $field = new TextTileField();
        $field->setName('price');
        $field->setWrapperClass("c4g-list-element__price-wrapper");
        $field->setClass("c4g-list-element__price");
        $fields[] = $field;
        
        $field = new TextTileField();
        $field->setName('beginDate');
        $field->setWrapperClass("c4g-list-element__begindate-wrapper");
        $field->setClass("c4g-list-element__begindate");
        $fields[] = $field;
        
        $field = new TextTileField();
        $field->setName('beginTime');
        $field->setWrapperClass("c4g-list-element__begintime-wrapper");
        $field->setClass("c4g-list-element__begintime");
        $fields[] = $field;
        
        $field = new TextTileField();
        $field->setName('maxCredit');
        $field->setFormat($GLOBALS['TL_LANG']['offer_list']['maxCredit_format']);
        $field->setWrapperClass("c4g-list-element__maxcredit-wrapper");
        $field->setClass("c4g-list-element__maxCredit");
        $fields[] = $field;
        
        $field = new TagTileField();
        $field->setName('tagLinks');
        $field->setWrapperClass("c4g-list-element__taglinks-wrapper");
        $field->setClass("c4g-list-element__taglinks");
        $field->setInnerClass("c4g-list-element__taglinks-image");
        $field->setLinkField("linkHref");
        $fields[] = $field;
        
        $field = new WrapperTileField();
        $field->setWrappedFields(['uuid', 'href']);
        $field->setClass("c4g-list-element__buttons-wrapper");
        $fields[] = $field;
        
        global $objPage;
        $settings = C4gSettingsModel::findSettings();
        $field = new ModalButtonTileField();
        $field->setName('cc');
        $field->setWrapperClass('c4g-list-element__clickcollect-wrapper');
        $field->setClass('c4g-list-element__clickcollect');
        $field->setLabel($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['modal_button_label']);
        $field->setUrl('/gutesio/operator/showcase_child_cc_form/'.$objPage->language.'/uuid');
        $field->setUrlField('uuid');
        $field->setConfirmButtonText($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['confirm_button_text']);
        $field->setCloseButtonText($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['close_button_text']);
        $field->setSubmitUrl(rtrim($settings->con4gisIoUrl, '/').self::CC_FORM_SUBMIT_URL);
        $field->setCondition('clickCollect', '1');
        $field->setCondition('type', 'product');
        $field->setCondition('type', 'showcase', true);
        $field->setInnerFields([
            'imageList',
            'name',
            'types'
        ]);
        $fields[] = $field;
        
        $field = new LinkButtonTileField();
        $field->setName("uuid");
        $field->setHrefFields(["type", "uuid"]);
        $field->setWrapperClass("c4g-list-element__notice-wrapper");
        $field->setClass("c4g-list-element__notice-link put-on-wishlist");
        $field->setHref("/gutesio/operator/wishlist/add/type/uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['offer_list']['frontend']['putOnWishlist']);
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->addConditionalClass("on_wishlist", "on-wishlist");
        $field->setAsyncCall(true);
        $field->setConditionField("not_on_wishlist");
        $field->setConditionValue('1');
        $field->setAddDataAttributes(true);
        $field->setHookAfterClick(true);
        $field->setHookName("addToWishlist");
        $fields[] = $field;
        
        $field = new LinkButtonTileField();
        $field->setName("uuid");
        $field->setHrefField("uuid");
        $field->setWrapperClass("c4g-list-element__notice-wrapper");
        $field->setClass("c4g-list-element__notice-link remove-from-wishlist");
        $field->setHref("/gutesio/operator/wishlist/remove/uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['offer_list']['frontend']['removeFromWishlist']);
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->setAsyncCall(true);
        $field->addConditionalClass("on_wishlist", "on-wishlist");
        $field->setConditionField("on_wishlist");
        $field->setConditionValue('1');
        $field->setAddDataAttributes(true);
        $field->setHookAfterClick(true);
        $field->setHookName("removeFromWishlist");
        $fields[] = $field;
        
        $detailLinks = $this->getOfferDetailLinks();
        $urlSuffix = Config::get('urlSuffix');
        foreach ($detailLinks as $key => $value) {
            $value = str_replace($urlSuffix, "", $value);
            $field = new LinkButtonTileField();
            $field->setName("href");
            $field->setWrapperClass("c4g-list-element__more-wrapper");
            $field->setClass("c4g-list-element__more-link");
            $field->setHrefFields(["href"]);
            $field->setLinkText($GLOBALS['TL_LANG']['gutesio_frontend']['learnMore']);
            $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
            $field->setHref($value . "/href" . $urlSuffix);
            $field->setExternalLinkField('foreignLink');
            $field->setExternalLinkFieldConditionField("directLink");
            $field->setExternalLinkFieldConditionValue("1");
            $field->setConditionField('type');
            $field->setConditionValue($key);
            $fields[] = $field;
        }
        
        return $fields;
    }
    
    private function getOfferDetailLinks(): array
    {
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $productPageModel = PageModel::findByPk($objSettings->productDetailPage);
        $eventPageModel = PageModel::findByPk($objSettings->eventDetailPage);
        $jobPageModel = PageModel::findByPk($objSettings->jobDetailPage);
        $arrangementPageModel = PageModel::findByPk($objSettings->arrangementDetailPage);
        $servicePageModel = PageModel::findByPk($objSettings->serviceDetailPage);
        $voucherPageModel = PageModel::findByPk($objSettings->voucherDetailPage);
        return [
            'product' => $productPageModel ? $productPageModel->getFrontendUrl() : '',
            'event' => $eventPageModel ? $eventPageModel->getFrontendUrl() : '',
            'job' => $jobPageModel ? $jobPageModel->getFrontendUrl() : '',
            'arrangement' => $arrangementPageModel ? $arrangementPageModel->getFrontendUrl() : '',
            'service' => $servicePageModel ? $servicePageModel->getFrontendUrl() : '',
            'voucher' => $voucherPageModel ? $voucherPageModel->getFrontendUrl() : '',
        ];
    }
    
    private function getChildTileData($childData, $request)
    {
        $database = Database::getInstance();
        $childRows = $database->prepare('SELECT a.id, a.parentChildId, a.uuid, a.tstamp, a.name, ' . '
        a.image, a.imageOffer, a.foreignLink, a.directLink, ' . '
            (CASE ' . '
                WHEN a.shortDescription IS NOT NULL THEN a.shortDescription ' . '
                WHEN b.shortDescription IS NOT NULL THEN b.shortDescription ' . '
                WHEN c.shortDescription IS NOT NULL THEN c.shortDescription ' . '
                WHEN d.shortDescription IS NOT NULL THEN d.shortDescription ' . '
            ELSE NULL END) AS shortDescription, ' . '
            tl_gutesio_data_child_type.type as type, tl_gutesio_data_child_type.name as typeName, e.clickCollect '.
            'FROM tl_gutesio_data_child a ' . '
            LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
            LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
            LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
            JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
            JOIN tl_gutesio_data_element e ON e.uuid = tl_gutesio_data_child_connection.elementId ' . '
            JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
            WHERE e.uuid = ?'
            . ' AND a.published = 1 AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP()) ORDER BY RAND()'
        )->execute($childData['elementId'])->fetchAllAssoc();
        
        foreach ($childRows as $key => $row) {
            if ($row['uuid'] === $childData['uuid']) {
                unset($childRows[$key]);
                continue;
            }
            $imageModel = $row['imageOffer'] && FilesModel::findByUuid($row['imageOffer']) ? FilesModel::findByUuid($row['imageOffer']) : FilesModel::findByUuid($row['image']);
            if ($imageModel !== null) {
                $childRows[$key]['image'] = [
                    'src' => $imageModel->path,
                    'alt' => $imageModel->meta && unserialize($imageModel->meta)['de'] ? unserialize($imageModel->meta)['de']['alt'] : $row['name']
                ];
                $row['image'] = [
                    'src' => $imageModel->path,
                    'alt' => $imageModel->meta && unserialize($imageModel->meta)['de'] ? unserialize($imageModel->meta)['de']['alt'] : $row['name']
                ];
            }
            unset($childRows[$key]['imageOffer']);
            unset($row['imageOffer']);
            
            $clientUuid = $this->checkCookieForClientUuid($request);
            if ($clientUuid !== null) {
                $db = Database::getInstance();
                $sql = "SELECT * FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ? AND `dataUuid` = ?";
                $result = $db->prepare($sql)->execute($clientUuid, $row['uuid'])->fetchAssoc();
                if ($result) {
                    $row['on_wishlist'] = "1";
                } else {
                    $row['not_on_wishlist'] = "1";
                }
            }

            if (!$row['tagLinks']) {
                $row['tagLinks'] = [];
            }
            
            $row['tagLinks'] = $childRows[$key]['tagLinks'];
            
            $result = $database->prepare('SELECT name, image, technicalKey FROM tl_gutesio_data_tag ' .
                'JOIN tl_gutesio_data_child_tag ON tl_gutesio_data_tag.uuid = tl_gutesio_data_child_tag.tagId ' .
                'WHERE tl_gutesio_data_tag.published = 1 AND tl_gutesio_data_child_tag.childId = ?')
                ->execute($row['uuid'])->fetchAllAssoc();
            foreach ($result as $r) {
                $model = FilesModel::findByUuid($r['image']);
                if ($model !== null) {
                    $icon = [
                        'name' => $r['name'],
                        'image' => [
                            'src' => $model->path,
                            'alt' => $r['name']
                        ]
                    ];
                    switch ($r['technicalKey']) {
                        case 'tag_delivery':
                            $stmt = $database->prepare(
                                'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                                'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                            $icon['linkHref'] = $stmt->execute(
                                $row['uuid'],
                                'deliveryServiceLink'
                            )->fetchAssoc()['tagFieldValue'];
                            $icon['linkLabel'] = 'Lieferservice';
                            break;
                        case 'tag_online_reservation':
                            $stmt = $database->prepare(
                                'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                                'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                            $icon['linkHref'] = $stmt->execute(
                                $row['uuid'],
                                'onlineReservationLink'
                            )->fetchAssoc()['tagFieldValue'];
                            $icon['linkLabel'] = 'Onlinereservierung';
                            break;
                        case 'tag_clicknmeet':
                            $stmt = $database->prepare(
                                'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                                'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                            $icon['linkHref'] = $stmt->execute(
                                $row['uuid'],
                                'clicknmeetLink'
                            )->fetchAssoc()['tagFieldValue'];
                            $icon['linkLabel'] = 'Click & Meet';
                            break;
                        case 'tag_table_reservation':
                            $stmt = $database->prepare(
                                'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                                'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                            $icon['linkHref'] = $stmt->execute(
                                $row['uuid'],
                                'tableReservationLink'
                            )->fetchAssoc()['tagFieldValue'];
                            $icon['linkLabel'] = 'Tischreservierung';
                            break;
                        case 'tag_onlineshop':
                            $stmt = $database->prepare(
                                'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                                'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                            $icon['linkHref'] = $stmt->execute(
                                $row['uuid'],
                                'onlineShopLink'
                            )->fetchAssoc()['tagFieldValue'];
                            $icon['linkLabel'] = 'Onlineshop';
                            break;
                        default:
                            break;
                    }
                    
                    $icon['linkHref'] = C4GUtils::addProtocolToLink($icon['linkHref']);
                    if (!$row['tagLinks']) {
                        $row['tagLinks'] = [];
                    }
                    $row['tagLinks'][] = $icon;
                }
            }
            
            $row['href'] = strtolower(str_replace(['{', '}'], '', $row['uuid']));
            if ($row['foreignLink']) {
                $row['foreignLink'] = C4GUtils::addProtocolToLink($row['foreignLink']);
            }
            $childRows[$key] = $row;
        }
        
        $childRows = $this->offerService->getAdditionalData($childRows);
        
        return $childRows;
    }
    
    private function getLocationElementData($locationElementUuid,$withExternal=false)
    {
        $showcaseData = $this->showcaseService->loadByUuid($locationElementUuid,$withExternal);

        $showcaseData['name'] = html_entity_decode($showcaseData['name']);
        
        return $showcaseData;
    }
}