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
use con4gis\FrameworkBundle\Classes\TileFields\TagTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TextTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TileField;
use con4gis\FrameworkBundle\Classes\TileFields\WrapperTileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use con4gis\FrameworkBundle\Traits\AutoItemTrait;
use con4gis\MapsBundle\Classes\MapDataConfigurator;
use con4gis\MapsBundle\Classes\ResourceLoader as MapsResourceLoader;
use Contao\Config;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Database;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OfferDetailModuleController extends \Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController
{
    use AutoItemTrait;

    protected $model = null;
    protected $request = null;
    protected $tileItems = [];

    /**
     * @var OfferLoaderService
     */
    private $offerService = null;

    private $languageRefs = [];

    const CC_FORM_SUBMIT_URL = '/showcase_child_cc_form_submit.php';
    const COOKIE_WISHLIST = "clientUuid";

    /**
     * OfferDetailModuleController constructor.
     * @param OfferLoaderService|null $offerService
     */
    public function __construct(?OfferLoaderService $offerService)
    {
        $this->offerService = $offerService;
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
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js?v=" . time(), ResourceLoader::BODY, "c4g-framework");
        $this->setupLanguage();
        ResourceLoader::loadCssResource("/bundles/con4gisframework/css/tiles.css");

        if ($this->model->gutesio_data_layoutType !== "plain") {
            ResourceLoader::loadCssResource("/bundles/gutesiooperator/css/c4g_detail.css");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/js/bootstrap.bundle.min.js");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/js/c4g_all.js");
            ResourceLoader::loadCssResource("/bundles/gutesiooperator/css/jquery.fancybox.min.css");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/js/jquery.fancybox.min.js");
        }

        if ($this->alias !== "") {
            $data = $this->offerService->getDetailData($this->alias);
            $conf = $this->getDetailFrontendConfiguration($data, $request);
            $conf->setLanguage($objPage->language);
            if (!empty($data)) {
                if ($this->model->gutesio_data_render_searchHtml) {
                    $sc = new SearchConfiguration();
                    $sc->addData($data, ['name', 'description']);
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


        return $template->getResponse();
    }

    private function setupLanguage()
    {
        System::loadLanguageFile("offer_list");
        System::loadLanguageFile("operator_showcase_list");
        System::loadLanguageFile("gutesio_frontend");
        $this->languageRefs = $GLOBALS['TL_LANG']['offer_list'];
    }

    private function getDetailFrontendConfiguration(array $data, Request $request)
    {
        $conf = new FrontendConfiguration('entrypoint_' . $this->model->id);
        $conf->addDetailPage(
            $this->getDetailPage(),
            $this->getDetailFields(),
            $data
        );
        $conf->addTileList(
            $this->getElementTileList(),
            $this->getElementFields(),
            $this->getElementData($data, $request)
        );

        return $conf;
    }


    protected function getDetailPage()
    {
        $page = new DetailPage();
        $page->setHeadline($GLOBALS['TL_LANG']['offer_list']['frontend']['details']['headline']);
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
        $page->setMapData($mapData);
        $page->setSections($this->getSections());
        $page->setShowAnchorMenu(true);
        $page->setMenuSectionIndex(2);
        return $page;
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

    protected function getDetailFields()
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
        $field->setName("appointmentUponAgreement");
        $field->setClass('detail-view__appointment-upon-agreement');
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

        $field = new DetailTextField();
        $field->setSection(5);
        $field->setName('taxNote');
        $field->setClass('taxNote detail-view__taxnote');
        $fields[] = $field;

        $field = new PDFDetailField();
        $field->setName("infoFile");
        $field->setLabel($this->languageRefs['infoFile_label']);
        $field->setTitle($this->languageRefs['infoFile_title']);
        $field->setClass("infoFile");
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

    protected function getElementTileList(): TileList
    {
        $this->tileList = new TileList('showcase-tiles');
        $this->tileList->setHeadline($this->languageRefs['offeredBy']);
        $this->tileList->setClassName("showcase-tiles c4g-list-outer");
        $this->tileList->setLayoutType("list");
        $this->tileList->setTileClassName("showcase-tile");
        return $this->tileList;
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
}