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
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use gutesio\OperatorBundle\Classes\Services\ServerService;
use gutesio\OperatorBundle\Classes\Services\ShowcaseService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ShowcaseDetailModuleController extends AbstractFrontendModuleController
{
    use AutoItemTrait;

    private array $languageRefs = [];

    private ModuleModel $model;

    public const TYPE = 'showcase_detail_module';
    public const COOKIE_WISHLIST = "clientUuid";

    /**
     *
     * ShowcaseDetailModuleController constructor.
     * @param ShowcaseService $showcaseService
     * @param UrlGeneratorInterface $generator
     * @param OfferLoaderService $offerLoaderService
     * @param ServerService $serverService
     * @param ContaoFramework $framework
     */
    public function __construct(
        private ShowcaseService $showcaseService,
        private UrlGeneratorInterface $generator,
        private OfferLoaderService $offerLoaderService,
        private ServerService $serverService,
        private ContaoFramework $framework
    ) {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $GLOBALS['TL_CONFIG']['useAutoItem'] = true;
        global $objPage;
        $this->model = $model;
        $elementUuid = 0;
        $elementUuids = StringUtil::deserialize($this->model->gutesio_data_elements, true);

        if ($this->model->gutesio_data_mode == "5" && count($elementUuids) && count($elementUuids) == 1) {
            $elementUuid = $elementUuids[0];
        } else {
            $this->setAlias($request);
        }

        $redirectPage = $model->gutesio_showcase_list_page;
        $redirectUrl = $redirectPage ? $this->generator->generate("tl_page." . $redirectPage) : '';

        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js", ResourceLoader::JAVASCRIPT, "c4g-framework");
        ResourceLoader::loadJavaScriptResource("/bundles/con4gismaps/build/c4g-maps.js", ResourceLoader::JAVASCRIPT, "c4g-maps");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_detail.min.css");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/bootstrap/util.js|async", ResourceLoader::JAVASCRIPT, "boostrap-util");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/bootstrap/modal.js|async", ResourceLoader::JAVASCRIPT, "boostrap-modal");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js|async", ResourceLoader::JAVASCRIPT, "c4g-all");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/detailmap.js|async", ResourceLoader::JAVASCRIPT, "detailmap");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/openinghours.js|async", ResourceLoader::JAVASCRIPT, "openinghours");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/phonehours.js|async", ResourceLoader::JAVASCRIPT, "phonehours");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/vendor/fancybox/jquery.fancybox.min.css");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/fancybox/jquery.fancybox.min.js|async");
        ResourceLoader::loadCssResource("/bundles/con4gisframework/dist/css/tiles.min.css");

        if ($this->model->gutesio_data_layoutType !== "plain") {
            ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing.min.css");
        }
        System::loadLanguageFile("operator_showcase_list");
        System::loadLanguageFile("offer_list");
        System::loadLanguageFile("gutesio_frontend");
        $this->languageRefs = $GLOBALS['TL_LANG']["operator_showcase_list"];

        if ($this->alias !== '' || $elementUuid) {
            MapsResourceLoader::loadResources(["router" => true], ['router_enable' => true]);
            $conf = new FrontendConfiguration('entrypoint_' . $model->id);
            $detailData = $this->getDetailData($request, $elementUuid);
            $request->getSession()->set('gutesio_element_alias', $detailData['alias']);
            $objPage->pageTitle = $detailData['name'];
            if (!empty($detailData)) {
                $detailData['internal_type'] = "showcase";
                $childData = $model->gutesio_without_tiles ? [] : $this->getChildTileData($request, $elementUuid ?: $detailData['uuid'], $model->gutesio_data_max_data);
                if ($childData !== null && count($childData) > 0) {
                    $template->hasOffers = true;
                }
                if (key_exists('imprintData', $detailData) && $detailData['imprintData']) {
                    $template->hasImprint = true;
                }
                $relatedShowcaseData = $this->getRelatedShowcaseData($detailData, $request);
                $relatedShowcaseTileList = $model->gutesio_without_tiles ? [] : $this->createRelatedShowcaseTileList();
                $relatedShowcaseFields = $model->gutesio_without_tiles ? [] : $this->getRelatedShowcaseTileFields();
                if (is_array($childData) && count($childData) > 0) {
                    $conf->addTileList($this->getChildTileList(), $this->getChildTileFields(), $childData);
                }
                if (count($relatedShowcaseData) > 0) {
                    $conf->addTileList($relatedShowcaseTileList, $relatedShowcaseFields, $relatedShowcaseData);
                    $template->hasRelatedShowcases = true;
                }
                $conf->setLanguage($objPage->language);
                $jsonConf = json_encode($conf);
                if ($jsonConf === false) {
                    C4gLogModel::addLogEntry("operator", json_last_error_msg());
                    $template->configuration = [];
                } else {
                    $template->configuration = $jsonConf;
                }
                $sc = new SearchConfiguration();
                $sc->addData($detailData, ['name', 'description', 'types', 'extendedSearchTerms']);
            } else {
                throw new RedirectResponseException($redirectUrl);
            }
            if ($model->gutesio_data_render_searchHtml) {
                $sc = new SearchConfiguration();
                $sc->addData($detailData, ['name', 'description', 'types', 'extendedSearchTerms']);
            }
        } else {
            if (C4GUtils::endsWith($this->pageUrl, $redirectUrl)) {
                return new Response();
            }
            throw new RedirectResponseException($redirectUrl);
        }

        if ($model->gutesio_load_klaro_consent) {
            $template->loadKlaro = true;
        }

        if (key_exists('imprintData', $detailData) && $detailData['imprintData']) {
            $template->imprintData = $detailData['imprintData'];
        }

        $template->entrypoint = 'entrypoint_' . $model->id;
        $template->detailData = $detailData;
        if ($model->gutesio_data_render_searchHtml) {
            $template->searchHTML = $sc->getHTML();
        }

        return $template->getResponse();
    }

    /**
     * @Route(
     *      path="/gutesio/operator/showcase_detail_get_map_data",
     *      name=ShowcaseDetailModuleController::class,
     *      methods={"GET"}
     *  )
     * @param Request $request
     * @param ContaoFramework $framework
     * @return JsonResponse
     */
    #[Route(path: '/gutesio/operator/showcase_detail_get_map_data', name: ShowcaseDetailModuleController::class, methods: ['GET'])]
    public function getMapData(Request $request, ContaoFramework $framework)
    {
        $framework->initialize();
        $settings = GutesioOperatorSettingsModel::findSettings();
        $mapData = MapDataConfigurator::prepareMapData(
            ContentModel::findById($settings->detail_map),
            Database::getInstance(),
            ["profile" => $settings->detail_profile],
            false
        );

        $mapData['geopicker']['input_geo_x'] = "#geox";
        $mapData['geopicker']['input_geo_y'] = "#geoy";
        $mapData['addIdToDiv'] = false;

        $strPublishedElem = ' AND (NOT {{table}}.releaseType = "external") AND ({{table}}.publishFrom IS NULL OR {{table}}.publishFrom < ' . time() . ') AND ({{table}}.publishUntil IS NULL OR {{table}}.publishUntil > ' . time() . ')';
        $strPublishedElem = str_replace('{{table}}', 'elem', $strPublishedElem);
        $strQueryElem = 'SELECT elem.* FROM tl_gutesio_data_element AS elem WHERE elem.alias =?' . $strPublishedElem;
        $alias = $request->getSession()->get('gutesio_element_alias', '');

        if (!$alias) {
            $alias = $_SERVER['HTTP_REFERER'];
            $strC = substr_count($alias, '/');
            $arrUrl = explode('/', $alias);

            if (strpos($arrUrl[$strC], '.html')) {
                $alias = substr($arrUrl[$strC], 0, strpos($arrUrl[$strC], '.html'));
            } else {
                $alias = $arrUrl[$strC];
            }
            if (strpos($alias, '?')) {
                $alias = explode('?', $alias)[0];
            }
        }

        if (C4GUtils::isValidGUID($alias)) {
            $offerConnections = Database::getInstance()->prepare('SELECT elementId FROM tl_gutesio_data_child_connection WHERE childId = ?')
                ->execute('{' . strtoupper($alias) . '}')->fetchAllAssoc();
            if ($offerConnections and (count($offerConnections) > 0)) {
                $firstConnection = $offerConnections[0];
                $elem = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_element WHERE `uuid` = ?')
                    ->execute($firstConnection['elementId'])->fetchAssoc();
            }
        } else {
            $elem = Database::getInstance()->prepare($strQueryElem)->execute($alias)->fetchAssoc();
        }
        if ($elem && $elem['geox'] && $elem['geoy']) {
          $mapData['center']['lon'] = $elem['geox'];
          $mapData['center']['lat'] = $elem['geoy'];
        }

        if ($elem && ($elem['geojson'] ?? null)) {
            $mapData['layers'][] = [
                'id' => 'current_element_geojson',
                'type' => 'GeoJSON',
                'format' => 'GeoJSON',
                'data' => json_decode($elem['geojson'], true),
                'active' => true,
                'display' => true,
                'content' => [
                    [
                        'type' => 'GeoJSON',
                        'format' => 'GeoJSON',
                        'data' => json_decode($elem['geojson'], true)
                    ]
                ]
            ];
        }

        return new JsonResponse($mapData);
    }

    private function getDetailData(Request $request, $elementUuid = 0): array
    {
        $typeIds = [];
        $this->framework->initialize();
        if ($this->model->gutesio_data_mode == '1') {
            $typeIds = unserialize($this->model->gutesio_data_type);
        }
        if ($elementUuid) {
            $detailData = $this->showcaseService->loadByUuid($elementUuid) ?: [];
        } else {
            $detailData = $this->showcaseService->loadByAlias($this->alias, $typeIds) ?: [];
        }

        if (count($detailData) === 0) {
            return [];
        }
        $db = Database::getInstance();
        $clientUuid = $this->checkCookieForClientUuid($request);
        if ($clientUuid !== null) {
            $sql = "SELECT * FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ? AND `dataUuid` = ?";
            $result = $db->prepare($sql)->execute($clientUuid, $detailData['uuid'])->fetchAssoc();
            if ($result) {
                $detailData['on_wishlist'] = 1;
            } else {
                $detailData['on_wishlist'] = 0;
            }
        }
        $detailData['opening_hours'] = str_replace("\\", "", $detailData['opening_hours']);
        $strTypes = "";
        $types = $detailData['types'];
        if ($types && is_array($types)) {
            foreach ($types as $key => $type) {
                $strTypes .= $type['label'];
                if ($key !== array_key_last($types)) {
                    $strTypes .= ", ";
                }
            }
        }
        $detailData['displayType'] = $strTypes;
        if (key_exists('relatedShowcaseLogos', $detailData) && is_array($detailData['relatedShowcaseLogos'])) {
            $relatedShowcases = $detailData['relatedShowcases'];
            foreach ($detailData['relatedShowcaseLogos'] as $key => $relatedShowcaseLogo) {
                if ($relatedShowcases[$key]['releaseType'] === "external") {
                    $detailData['relatedShowcaseLogos'][$key]['href'] = C4GUtils::addProtocolToLink($relatedShowcases[$key]['foreignLink']);
                } else {
                    $url = $this->pageUrl;
                    $detailData['relatedShowcaseLogos'][$key]['href'] = $url . "/" . $relatedShowcaseLogo['href'];
                }
            }
        }
        unset($detailData['relatedShowcases']);

        foreach ($detailData as $key => $detailDatum) {
            if (strpos(strtoupper($key), 'LINK')) {
                $detailData[$key] = C4GUtils::addProtocolToLink($detailDatum);
            }
        }

        // load extendedSearchTerms
        $typeParameters = [];

        if ($types && is_array($types)) {
            foreach ($types as $type) {
                if ($type['uuid']) {
                    $typeParameters[] = $type['uuid'];
                }
            }
            $typeInString = C4GUtils::buildInString($typeParameters);
            $sql = "SELECT `extendedSearchTerms` FROM tl_gutesio_data_type WHERE `uuid` " . $typeInString;
            $arrSearchTerms = $db->prepare($sql)->execute(...$typeParameters)->fetchAllAssoc();
            $strSearchTerms = "";
            foreach ($arrSearchTerms as $searchTerm) {
                $strSearchTerms .= $searchTerm['extendedSearchTerms'] . ",";
            }
            $detailData['extendedSearchTerms'] = str_replace(",", " ", $strSearchTerms);
        }

        return $detailData;
    }

    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request->cookies->get(self::COOKIE_WISHLIST);
        return $clientUuidCookie === null ? null : $clientUuidCookie;
    }

    private function getChildTileList()
    {
        $tileList = new TileList();
        $tileList->setHeadline($GLOBALS['TL_LANG']["operator_showcase_list"]['our_offers']);
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
        $fileUtils = new FileUtils();
        foreach ($detailLinks as $key => $value) {
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

        foreach ($detailLinks as $key => $value) {
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

        $user = FrontendUser::getInstance();
        $page = $this->model->cart_page ?: 0;
        if ($page !== 0) {
            $page = PageModel::findByPk($page);
            if ($page) {
                $purchasableOfferTypeCondition = new OrCondition();
                $purchasableOfferTypeCondition->addConditions(
                    new FieldValueCondition('type', 'product'),
                    new FieldValueCondition('type', 'voucher')
                );
                $baseUrl = $this->serverService->getMainServerURL();
                if (!preg_match('#^https?://#i', $baseUrl)) {
                    $baseUrl = 'https://' . ltrim($baseUrl, '/');
                }

                $field = new LinkButtonTileField();
                $field->setName("uuid");
                $field->setWrapperClass("c4g-list-element__cart-wrapper");
                $field->setClass("c4g-list-element__cart-link put-in-cart");
                $field->setHref(rtrim($baseUrl, '/') . '/gutesio/main/cart/add');
                $field->setLinkText($this->languageRefs['frontend']['putInCart']);
                $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
                $field->addConditionalClass("in_cart", "in-cart");
                $field->setAsyncCall(true);
                $field->addCondition(new FieldValueCondition('offerForSale', '1'));
                $field->addCondition(new FieldNotValueCondition('rawPrice', ''));
                $field->addCondition(new FieldNotValueCondition('rawPrice', '0'));
                $field->addCondition(new FieldNotValueCondition('priceStartingAt', '1'));
                $field->addCondition(new FieldNotValueCondition('availableAmount', '0'));
                $field->addCondition(new FieldNotValueCondition('ownerMemberId', (string)$user->id));
                $field->setAddDataAttributes(true);
                $field->setHookAfterClick(true);
                $field->setHookName("addToCart");

                $field->setRedirectPageOnSuccess($page->getAbsoluteUrl());
                $fields[] = $field;

                $field = new TextTileField();
                $field->setName("uuid");
                $field->setWrapperClass("c4g-list-element__cart-wrapper");
                $field->setClass("c4g-list-element__cart-link not-available");
                $field->setFormat('Zurzeit nicht verfügbar');
                $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
                $field->addCondition(new FieldValueCondition('offerForSale', '1'));
                $field->addCondition(new FieldNotValueCondition('rawPrice', ''));
                $field->addCondition(new FieldNotValueCondition('rawPrice', '0'));
                $field->addCondition(new FieldNotValueCondition('priceStartingAt', '1'));
                $field->addCondition(new FieldValueCondition('availableAmount', '0'));
                $field->addCondition(new FieldNotValueCondition('ownerMemberId', (string) $user->id));
                $fields[] = $field;
            }
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

    private function getChildTileData($request, $elementUuid = 0, $maxCount = 0)
    {
        if ($elementUuid) {
            $childRows = $this->offerLoaderService->loadOffersForShowcase($elementUuid, null, $maxCount);
        } else {
            // function is not called without $elementUuid currently
        }

        return $childRows;
    }

    private function getRelatedShowcaseData($arrShowcase, $request): array
    {
        $relatedShowcases = $this->showcaseService->loadRelatedShowcases($arrShowcase);

        foreach ($relatedShowcases as $key => $row) {
            $types = [];
            foreach ($row['types'] as $type) {
                $types[] = $type['label'];
                $row['types'] = implode(', ', $types);
            }

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

            $relatedShowcases[$key] = $row;
        }

        return $relatedShowcases;
    }

    private function createRelatedShowcaseTileList()
    {
        $list = new TileList();
        $list->setName("related-showcases");
        $list->setLayoutType("list");
        $list->setClassName("related-showcase-list c4g-list-outer");
        $list->setTileClassName("container");
        $list->setHeadline($GLOBALS['TL_LANG']['operator_showcase_list']['alsoInteresting']);
        $list->setListWrapper(true);
        $list->setWrapperId("related-showcase-tiles");
        $list->setHeadlineLevel(2);

        return $list;
    }

    private function getRelatedShowcaseTileFields()
    {
        $fields = [];

        if (C4GUtils::endsWith($this->pageUrl, '.html')) {
            $href = str_replace('.html', '/alias.html', $this->pageUrl);
        } else {
            $href = $this->pageUrl . '/alias';
        }

        $field = new ImageTileField();
        $field->setName("image");
        $field->setWrapperClass("c4g-list-element__image-wrapper");
        $field->setClass("c4g-list-element__image");
        $field->setHrefField("alias");
        $field->setHref($href);
        $field->setExternalLinkField('foreignLink');
        $field->setExternalLinkFieldConditionField("directLink");
        $field->setExternalLinkFieldConditionValue("1");
        $fields[] = $field;

        $field = new HeadlineTileField();
        $field->setName("name");
        $field->setWrapperClass("c4g-list-element__title-wrapper");
        $field->setClass("c4g-list-element__title");
        $field->setLevel(3);
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName("types");
        $field->setWrapperClass("c4g-list-element__types-wrapper");
        $field->setClass("c4g-list-element__types");
        $fields[] = $field;

        $field = new TagTileField();
        $field->setName("tags");
        $field->setWrapperClass("c4g-list-element__tags-wrapper");
        $field->setClass("c4g-list-element__tag");
        $field->setInnerClass("c4g-list-element__tag-image");
        $field->setLinkField("linkHref");
        $fields[] = $field;

        $field = new DistanceField();
        $field->setName("distance");
        $field->setWrapperClass("c4g-list-element__distance-wrapper");
        $field->setClass("c4g-list-element__distance");
        $field->setLabel($this->languageRefs['distance'][0]);
        $field->setGeoxField("geox");
        $field->setGeoyField("geoy");
        $fields[] = $field;

        $field = new WrapperTileField();
        $field->setWrappedFields(['uuid', 'alias']);
        $field->setClass("c4g-list-element__buttons-wrapper");
        $fields[] = $field;

        $field = new LinkButtonTileField();
        $field->setName("uuid");
        $field->setHrefField("uuid");
        $field->setWrapperClass("c4g-list-element__notice-wrapper");
        $field->setClass("c4g-list-element__notice-link put-on-wishlist");
        $field->setHref("/gutesio/operator/wishlist/add/showcase/uuid");
        $field->setLinkText($this->languageRefs['putOnWishlist']);
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->addConditionalClass("on_wishlist", "on-wishlist");
        $field->setAsyncCall(true);
        $field->setConditionField("not_on_wishlist");
        $field->setConditionValue(true);
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
        $field->setLinkText($this->languageRefs['removeFromWishlist']);
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->setAsyncCall(true);
        $field->addConditionalClass("on_wishlist", "on-wishlist");
        $field->setConditionField("on_wishlist");
        $field->setConditionValue(true);
        $field->setAddDataAttributes(true);
        $field->setHookAfterClick(true);
        $field->setHookName("removeFromWishlist");
        $fields[] = $field;

        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setHrefField("alias");
        $field->setWrapperClass("c4g-list-element__more-wrapper");
        $field->setClass("c4g-list-element__more-link");
        $field->setHref($href);
        $field->setLinkText($this->languageRefs['alias_link_text']);
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->setExternalLinkField('foreignLink');
        $field->setExternalLinkFieldConditionField("directLink");
        $field->setExternalLinkFieldConditionValue("1");
        $fields[] = $field;

        return $fields;
    }
}
