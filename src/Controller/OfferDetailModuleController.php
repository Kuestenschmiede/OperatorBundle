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
use con4gis\FrameworkBundle\Classes\DetailFields\DetailContactField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailFancyboxImageGallery;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailHeadlineField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailHTMLField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailMapLocationField;
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
use con4gis\FrameworkBundle\Classes\Utility\FieldUtil;
use con4gis\FrameworkBundle\Traits\AutoItemTrait;
use con4gis\MapsBundle\Classes\MapDataConfigurator;
use con4gis\MapsBundle\Classes\ResourceLoader as MapsResourceLoader;
use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Database;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\DataModelBundle\Classes\TypeDetailFieldGenerator;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use gutesio\OperatorBundle\Classes\Services\ServerService;
use gutesio\OperatorBundle\Classes\Services\ShowcaseService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OfferDetailModuleController extends AbstractFrontendModuleController
{
    use AutoItemTrait;

    private ?Request $request = null;
    private array $tileItems = [];
    private array $languageRefs = [];

    public const COOKIE_WISHLIST = "clientUuid";

    /**
     * OfferDetailModuleController constructor.
     * @param OfferLoaderService|null $offerService
     * @param ShowcaseService $showcaseService
     * @param ServerService $serverService
     */
    public function __construct(
        private ContaoFramework $framework,
        private ?OfferLoaderService $offerService,
        private ShowcaseService $showcaseService,
        private ServerService $serverService,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $GLOBALS['TL_CONFIG']['useAutoItem'] = true;
        global $objPage;
        $this->framework->initialize();
        $this->offerService->setModel($model);
        $this->setAlias($request);
        $pageUrl = "";
        $page = PageModel::findByPk($model->gutesio_offer_list_page);
        if ($page) {
            $pageUrl = $page->getAbsoluteUrl();
        }
        $this->offerService->setPageUrl($pageUrl);
        $this->offerService->setRequest($request);
        $this->request = $request;

        $redirectPage = $model->gutesio_offer_list_page;
        $redirectUrl = $redirectPage ? $this->urlGenerator->generate("tl_page." . $redirectPage) : $pageUrl;

        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js", ResourceLoader::JAVASCRIPT, "c4g-framework");

        if (!$model->gutesio_without_contact) {
            ResourceLoader::loadJavaScriptResource("/bundles/con4gismaps/build/c4g-maps.js", ResourceLoader::JAVASCRIPT, "c4g-maps");
        }

        $this->setupLanguage();
        ResourceLoader::loadCssResource("/bundles/con4gisframework/dist/css/tiles.min.css");

        if ($model->gutesio_data_layoutType !== "plain") {
            ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_detail.min.css");
            if (!$model->gutesio_without_contact) {
                ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/openinghours.js|async", ResourceLoader::JAVASCRIPT, "openinghours");
                ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/phonehours.js|async", ResourceLoader::JAVASCRIPT, "phonehours");
            }
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/bootstrap/util.js|async", ResourceLoader::JAVASCRIPT, "boostrap-util");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/bootstrap/modal.js|async", ResourceLoader::JAVASCRIPT, "boostrap-modal");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js|async|static", ResourceLoader::JAVASCRIPT, "c4g-all");

            if (!$model->gutesio_without_contact) {
                ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/detailmap.js|async", ResourceLoader::JAVASCRIPT, "detailmap");
            }

            ResourceLoader::loadCssResource("/bundles/gutesiooperator/vendor/fancybox/jquery.fancybox.min.css");
            ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/fancybox/jquery.fancybox.min.js|async");
        }

        if ($this->alias !== "") {
            $typeKeys = [];
            if ($model->gutesio_child_data_mode == '1') {
                $typeKeys = unserialize($model->gutesio_child_type);
            }
            $data = $this->offerService->getDetailData($this->alias, $typeKeys) ?: [];
            $template->loggedIn = FrontendUser::getInstance()->id > 0;
            $template->offerForSale = $data['offerForSale'];
            $cartPage = GutesioOperatorSettingsModel::findSettings()->cartPage;
            $cartPage = PageModel::findByPk($cartPage);
            $template->cartPageUrl = $cartPage ? $cartPage->getAbsoluteUrl() : '';
            $template->addToCartUrl = $this->serverService->getMainServerURL().'/gutesio/main/cart/add';
            $template->childId = $data['uuid'];
            $template->elementId = $data['elementId'];
            if ($data) {
                $objPage->pageTitle = $data['name'];
                $conf = new FrontendConfiguration('entrypoint_' . $model->id);
                $components = $this->getDetailComponents($data, $request);
                if ($data['type'] === "event" && !$model->gutesio_without_tiles) {
                    $elementUuid = $components['elements'][2][0]['uuid'];
                    if ($data['locationElementId'] || $elementUuid) {
                        $locationElementData = $this->getLocationElementData($data['locationElementId'] ?: $elementUuid, true);
                        if ($locationElementData && is_array($locationElementData) && key_exists('name', $locationElementData) && $locationElementData['name']) {
                            $objSettings = GutesioOperatorSettingsModel::findSettings();
                            $data['locationUrl'] = $objSettings->showcaseDetailPage ? C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->showcaseDetailPage . "}}") . '/' . $locationElementData['alias'] : '';
                            if ($data['locationElementId'] && $elementUuid && ($elementUuid !== $data['locationElementId'])) {
                                $locationList = $this->getLocationList();

                                if ($locationList) {
                                    $conf->addTileList(
                                        $locationList,
                                        $this->tileItems,
                                        [$locationElementData]
                                    );
                                }
                            }
                        }
                    }
                }

                if (!$model->gutesio_without_tiles) {
                    $conf->addTileList(
                        $components['elements'][0],
                        $components['elements'][1],
                        $components['elements'][2]
                    );
                    $otherChildData = $this->getChildTileData($data, $request, $model->limit_detail_offers);
                    $childList = [];
                    //remove duplicated offers
                    foreach ($otherChildData as $key=>$resultData) {
                        $childList[$resultData['id']] = $resultData;
                    }

                    $otherChildData = array_values($childList);

                    if (count($otherChildData) > 0) {
                        $conf->addTileList(
                            $this->getChildTileList(),
                            $this->getChildTileFields(),
                            $otherChildData
                        );
                        $template->hasOtherChilds = true;
                    }
                }
                $conf->setLanguage($objPage->language);
                if (!empty($data)) {
                    if ($model->gutesio_data_render_searchHtml) {
                        $sc = new SearchConfiguration();
                        $sc->addData($data, ['name', 'description', 'displayType', 'extendedSearchTerms']);
                    }
                } else {
                    throw new RedirectResponseException($redirectUrl);
                }
                $template->entrypoint = 'entrypoint_' . $model->id;
                $strConf = json_encode($conf);
                $error = json_last_error_msg();
                if ($error && (strtoupper($error) !== "NO ERROR")) {
                    C4gLogModel::addLogEntry("operator", $error);
                }
                $template->configuration = $strConf;
                if ($model->gutesio_data_render_searchHtml) {
                    $template->searchHTML = $sc->getHTML();
                }
            } else {
                throw new RedirectResponseException($redirectUrl);
            }
        } else {
            throw new RedirectResponseException($redirectUrl);
        }
        $template->detailData = $data;
        if (!$model->gutesio_without_contact) {
            $template->mapData = $this->getMapData();
        }
        $template->model = $model;
        $page = $model->cart_page ?: 0;
        if ($page !== 0) {
            $page = PageModel::findByPk($page);
            if ($page) {
                $template->cartUrl = $page->getAbsoluteUrl();
            }
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

    private function getDetailComponents(array $data, Request $request)
    {
        $components = [];
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

        if (!$this->offerService->getModel()->gutesio_without_contact) {
            $section = new DetailPageSection($this->languageRefs['contact'], true, "detail-view__section-contact", true);
            $sections[] = $section;
        }

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
        $field->setName("isbn");
        $field->setLabel($this->languageRefs['isbn'][0]);
        $field->setClass('detail-view__isbn');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("ean");
        $field->setLabel($this->languageRefs['ean'][0]);
        $field->setClass('detail-view__ean');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("brand");
        $field->setLabel($this->languageRefs['brand'][0]);
        $field->setClass('detail-view__brand');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("basePriceUnit");
        $field->setLabel($this->languageRefs['basePriceUnit'][0]);
        $field->setClass('detail-view__basePriceUnit');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("basePriceUnitPerPiece");
        $field->setLabel($this->languageRefs['basePriceUnitPerPiece'][0]);
        $field->setClass('detail-view__basePriceUnitPerPiece');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("allergenes");
        $field->setLabel($this->languageRefs['allergenes'][0]);
        $field->setClass('detail-view__allergenes');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("ingredients");
        $field->setLabel($this->languageRefs['ingredients'][0]);
        $field->setClass('detail-view__ingredients');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("kJ");
        $field->setLabel($this->languageRefs['kJ'][0]);
        $field->setClass('detail-view__kJ');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("fat");
        $field->setLabel($this->languageRefs['fat'][0]);
        $field->setClass('detail-view__fat');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("saturatedFattyAcid");
        $field->setLabel($this->languageRefs['saturatedFattyAcid'][0]);
        $field->setClass('detail-view__saturatedFattyAcid');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("carbonHydrates");
        $field->setLabel($this->languageRefs['carbonHydrates'][0]);
        $field->setClass('detail-view__carbonHydrates');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("sugar");
        $field->setLabel($this->languageRefs['sugar'][0]);
        $field->setClass('detail-view__sugar');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("salt");
        $field->setLabel($this->languageRefs['salt'][0]);
        $field->setClass('detail-view__salt');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("availableAmount");
        $field->setLabel($this->languageRefs['availableAmount'][0]);
        $field->setClass('detail-view__availableAmount');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("dateOfBirth");
        $field->setClass('detail-view__dateOfBirth');
        $fields[] = $field;

        $field = new DetailTextField();
        $field->setSection(4);
        $field->setName("dateOfDeath");
        $field->setClass('detail-view__dateOfDeath');
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
        $field->setName("entryTime");
        $field->setClass('detail-view__entry-time');
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

        if (!$this->offerService->getModel()->gutesio_without_contact) {
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
        }

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
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $url = C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->showcaseDetailPage . "}}");

        $href = $url . '/alias';
        $urlSuffix = Config::get('urlSuffix');
        $href = str_replace($urlSuffix, "", $href);
        $href .= $urlSuffix;

        $field = new ImageTileField();
        $field->setName("image");
        $field->setRenderSection(TileField::RENDERSECTION_HEADER);
        $field->setWrapperClass("c4g-list-element__image-wrapper");
        $field->setClass("c4g-list-element__image");
        $field->setHrefField("alias");
        $field->setHref($href);
        $field->setExternalLinkField('foreignLink');
        $field->setExternalLinkFieldConditionField("directLink");
        $field->setExternalLinkFieldConditionValue("1");
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
//            foreach ($row as $rowKey => $rowValue) {
//               if ($data[$rowKey] && $rowKey != 'uuid' && $rowKey != 'id' && $rowKey != 'tstamp') {
//                    $row[$rowKey] = $data[$rowKey]; //do not override duplicated field names
//               }
//            }

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

            if (is_array($row['types']) && count($row['types']) > 0) {
                foreach ($row['types'] as $type) {
                    $types[] = $type['label'];
                    $row['types'] = implode(', ', $types);
                }
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

        $detailLinks = $this->getOfferDetailLinks();
        $urlSuffix = Config::get('urlSuffix');
        foreach ($detailLinks as $key => $value) {
            $value = str_replace($urlSuffix, "", $value);
            $field = new ImageTileField();
            $field->setWrapperClass("c4g-list-element__image-wrapper");
            $field->setClass("c4g-list-element__image");
            $field->setName('image');
            $field->setHrefFields(["href"]);
            $field->setHref($value . "/href" . $urlSuffix);
            $field->setExternalLinkField('foreignLink');
            $field->setExternalLinkFieldConditionField("directLink");
            $field->setExternalLinkFieldConditionValue("1");
            $field->setConditionField('type');
            $field->setConditionValue($key);
            $fields[] = $field;
        }

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

        /*
        $field = new TextTileField();
        $field->setName('releasedAtDisplay');
        $field->setWrapperClass("c4g-list-element__releasedat-wrapper");
        $field->setClass("c4g-list-element__releasedAt");
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('remoteTypeDisplay');
        $field->setWrapperClass("c4g-list-element__remotetype-wrapper");
        $field->setClass("c4g-list-element__remoteType");
        $fields[] = $field;
        */
        
        $field = new TextTileField();
        $field->setName('beginDateDisplay');
        $field->setWrapperClass("c4g-list-element__begindate-wrapper");
        $field->setClass("c4g-list-element__beginDate");
        $fields[] = $field;
        
        $field = new TextTileField();
        $field->setName('beginTimeDisplay');
        $field->setWrapperClass("c4g-list-element__begintime-wrapper");
        $field->setClass("c4g-list-element__beginTime");
        $fields[] = $field;

//        $field = new TextTileField();
//        $field->setName('entryTime');
//        $field->setWrapperClass("c4g-list-element__entrytime-wrapper");
//        $field->setClass("c4g-list-element__entrytime");
//        $fields[] = $field;
        
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
        
//        $detailLinks = $this->getOfferDetailLinks();
//        $urlSuffix = Config::get('urlSuffix');
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
        $personPageModel = PageModel::findByPk($objSettings->personDetailPage);
        $voucherPageModel = PageModel::findByPk($objSettings->voucherDetailPage);
        return [
            'product' => $productPageModel ? $productPageModel->getAbsoluteUrl() : '',
            'event' => $eventPageModel ? $eventPageModel->getAbsoluteUrl() : '',
            'job' => $jobPageModel ? $jobPageModel->getAbsoluteUrl() : '',
            'arrangement' => $arrangementPageModel ? $arrangementPageModel->getAbsoluteUrl() : '',
            'service' => $servicePageModel ? $servicePageModel->getAbsoluteUrl() : '',
            'person' => $personPageModel ? $personPageModel->getAbsoluteUrl() : '',
            'voucher' => $voucherPageModel ? $voucherPageModel->getAbsoluteUrl() : '',
        ];
    }
    
    private function getChildTileData($childData, $request, $limit)
    {
        $childRows = $this->offerService->loadOffersForShowcase($childData['elementId'], $childData['uuid'], $limit);

        foreach ($childRows as $row) {
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
        }
        
        return $childRows;
    }
    
    private function getLocationElementData($locationElementUuid,$withExternal=false)
    {
        $showcaseData = $this->showcaseService->loadByUuid($locationElementUuid,$withExternal);

        $showcaseData['name'] = html_entity_decode($showcaseData['name']);
        
        return $showcaseData;
    }
}