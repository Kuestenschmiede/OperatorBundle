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
use con4gis\FrameworkBundle\Classes\DetailFields\DetailHTMLField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailImageLinkField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailLinkField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailMapLocationField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailTagField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailTextField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailVideoPreviewField;
use con4gis\FrameworkBundle\Classes\DetailPage\DetailAnchorMenuLink;
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
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShowcaseDetailModuleController extends \Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController
{
    use AutoItemTrait;

    const TYPE = 'showcase_detail_module';
    const COOKIE_WISHLIST = "clientUuid";
    const CC_FORM_SUBMIT_URL = '/showcase_child_cc_form_submit.php';

    protected $model = null;

    private $showcaseService = null;

    private $languageRefs = [];

    /**
     * @var UrlGeneratorInterface
     */
    private $generator;

    /**
     * @var OfferLoaderService
     */
    private $offerLoaderService;


    /**
     * ShowcaseDetailModuleController constructor.
     * @param ShowcaseService $showcaseService
     * @param UrlGeneratorInterface $generator
     * @param OfferLoaderService $offerLoaderService
     */
    public function __construct(
        ShowcaseService $showcaseService,
        UrlGeneratorInterface $generator,
        OfferLoaderService $offerLoaderService
    ) {
        $this->showcaseService = $showcaseService;
        $this->generator = $generator;
        $this->offerLoaderService = $offerLoaderService;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        global $objPage;
        $this->model = $model;
        $this->setAlias();
        $redirectPage = $model->gutesio_showcase_list_page;
        $redirectUrl = $this->generator->generate("tl_page." . $redirectPage);
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js", ResourceLoader::JAVASCRIPT, "c4g-framework");
        ResourceLoader::loadJavaScriptResource("/bundles/con4gismaps/build/c4g-maps.js", ResourceLoader::JAVASCRIPT, "c4g-maps");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_detail.min.css");
//        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/jquery/jquery-3.5.1.slim.min.js|async|static?v=" . time(), ResourceLoader::JAVASCRIPT, "boostrap-jquery");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/bootstrap/util.js|async", ResourceLoader::JAVASCRIPT, "boostrap-util");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/bootstrap/modal.js|async", ResourceLoader::JAVASCRIPT, "boostrap-modal");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js|async", ResourceLoader::JAVASCRIPT, "c4g-all");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/detailmap.js|async", ResourceLoader::JAVASCRIPT, "detailmap");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/openinghours.js|async", ResourceLoader::JAVASCRIPT, "openinghours");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/phonehours.js|async", ResourceLoader::JAVASCRIPT, "phonehours");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/vendor/fancybox/jquery.fancybox.min.css");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/fancybox/jquery.fancybox.min.js|async");
        System::loadLanguageFile("operator_showcase_list");
        System::loadLanguageFile("offer_list");
        System::loadLanguageFile("gutesio_frontend");
        $this->languageRefs = $GLOBALS['TL_LANG']["operator_showcase_list"];

        if ($this->alias !== '') {
            MapsResourceLoader::loadResources(["router" => true], ['router_enable' => true]);
            $conf = new FrontendConfiguration('entrypoint_' . $this->model->id);
            $detailData = $this->getDetailData($request);
            $objPage->pageTitle = $detailData['name'];
            if (!empty($detailData)) {
                $detailData['internal_type'] = "showcase";
                $childData = $this->getChildTileData($request);
                if (count($childData) > 0) {
                    $template->hasOffers = true;
                }
                if ($detailData['imprintData']) {
                    $template->hasImprint = true;
                }
                $relatedShowcaseData = $this->getRelatedShowcaseData($detailData, $request);
                $relatedShowcaseTileList = $this->createRelatedShowcaseTileList();
                $relatedShowcaseFields = $this->getRelatedShowcaseTileFields();
                if (count($childData) > 0) {
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
            if ($this->model->gutesio_data_render_searchHtml) {
                $sc = new SearchConfiguration();
                $sc->addData($detailData, ['name', 'description', 'types', 'extendedSearchTerms']);
            }
        } else {
            throw new RedirectResponseException($redirectUrl);
        }
        
        if ($this->model->gutesio_load_klaro_consent) {
            $template->loadKlaro = true;
        }
        
        if ($detailData['imprintData']) {
            $template->imprintData = $detailData['imprintData'];
        }

        $template->entrypoint = 'entrypoint_' . $this->model->id;
        $template->detailData = $detailData;
        if ($this->model->gutesio_data_render_searchHtml) {
            $template->searchHTML = $sc->getHTML();
        }

        return $template->getResponse();
    }
    
    /**
     * @Route("/gutesio/operator/showcase_detail_get_map_data", name="showcase_detail_get_map_data", methods={"GET"})
     * @param Request $request
     * @param ContaoFramework $framework
     * @param $offset
     * @return JsonResponse
     */
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
        
        return new JsonResponse($mapData);
    }

    private function getDetailData(Request $request): array
    {
        $detailData = $this->showcaseService->loadByAlias($this->alias) ?: [];
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
        if ($detailData['relatedShowcaseLogos'] && is_array($detailData['relatedShowcaseLogos'])) {
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
        foreach ($types as $type) {
            $typeParameters[] = $type['value'];
        }
        $typeInString = C4GUtils::buildInString($types);
        $sql = "SELECT `extendedSearchTerms` FROM tl_gutesio_data_type WHERE `id` " . $typeInString;
        $arrSearchTerms = $db->prepare($sql)->execute($typeParameters)->fetchAllAssoc();
        $strSearchTerms = "";
        foreach ($arrSearchTerms as $searchTerm) {
            $strSearchTerms .= $searchTerm['extendedSearchTerms'] . ",";
        }
        $detailData['extendedSearchTerms'] = str_replace(",", " ", $strSearchTerms);
        
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

    private function getChildTileData($request)
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
            WHERE e.alias = ?'
            . ' AND a.published = 1 AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP()) ORDER BY RAND()'
        )->execute($this->alias)->fetchAllAssoc();

        foreach ($childRows as $key => $row) {
            $imageModel = $row['imageOffer'] && FilesModel::findByUuid($row['imageOffer']) ? FilesModel::findByUuid($row['imageOffer']) : FilesModel::findByUuid($row['image']);
            if ($imageModel !== null) {
                list($width, $height) = getimagesize($imageModel->path);
                $childRows[$key]['image'] = [
                    'src' => $imageModel->path,
                    'alt' => $imageModel->meta && unserialize($imageModel->meta)['de'] ? unserialize($imageModel->meta)['de']['alt'] : $row['name'],
                    'width' => $width,
                    'height' => $height,
                ];
                $row['image'] = [
                    'src' => $imageModel->path,
                    'alt' => $imageModel->meta && unserialize($imageModel->meta)['de'] ? unserialize($imageModel->meta)['de']['alt'] : $row['name'],
                    'width' => $width,
                    'height' => $height,
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
                            'alt' => $r['name'],
                            'width' => 100,
                            'height' => 100,
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

        $childRows = $this->offerLoaderService->getAdditionalData($childRows);

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

        $field = new ImageTileField();
        $field->setName("imageList");
        $field->setWrapperClass("c4g-list-element__image-wrapper");
        $field->setClass("c4g-list-element__image");
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

        if (C4GUtils::endsWith($this->pageUrl, '.html')) {
            $href = str_replace('.html', '/alias.html', $this->pageUrl);
        } else {
            $href = $this->pageUrl . '/alias';
        }
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