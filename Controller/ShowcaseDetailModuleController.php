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
use con4gis\FrameworkBundle\Classes\DetailFields\DetailContactField;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailFancyboxImageGallery;
use con4gis\FrameworkBundle\Classes\DetailFields\DetailHTMLField;
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
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Database;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\DataModelBundle\Classes\TypeDetailFieldGenerator;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataElementModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\ShowcaseService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShowcaseDetailModuleController extends \Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController
{
    use AutoItemTrait;

    const TYPE = 'showcase_detail_module';
    const COOKIE_WISHLIST = "clientUuid";

    protected $model = null;

    private $showcaseService = null;

    private $languageRefs = [];

    /**
     * @var UrlGeneratorInterface
     */
    private $generator;


    /**
     * ShowcaseDetailModuleController constructor.
     * @param ShowcaseService $showcaseService
     * @param UrlGeneratorInterface $generator
     */
    public function __construct(ShowcaseService $showcaseService, UrlGeneratorInterface $generator)
    {
        $this->showcaseService = $showcaseService;
        $this->generator = $generator;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        global $objPage;
        $this->model = $model;
        $this->setAlias();
        $redirectPage = $model->gutesio_showcase_list_page;
        $redirectUrl = $this->generator->generate("tl_page." . $redirectPage);
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js?v=" . time(), ResourceLoader::BODY, "c4g-framework");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/css/c4g_detail.css");
//        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/js/jquery-3.5.1.slim.min.js");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/js/bootstrap.bundle.min.js");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/js/c4g_all.js");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/css/jquery.fancybox.min.css");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/js/jquery.fancybox.min.js");
        System::loadLanguageFile("operator_showcase_list");
        System::loadLanguageFile("offer_list");
        System::loadLanguageFile("gutesio_frontend");
        $this->languageRefs = $GLOBALS['TL_LANG']["operator_showcase_list"];

        if ($this->alias !== '') {
            $conf = new FrontendConfiguration('entrypoint_' . $this->model->id);
            $detailData = $this->getDetailData($request);
            if (!empty($detailData)) {
                $detailData['internal_type'] = "showcase";
                $detailPage = $this->getDetailPage();
                $childData = $this->getChildTileData($request);
                if (count($childData) > 0) {
                    $link = new DetailAnchorMenuLink(
                        $GLOBALS['TL_LANG']["operator_showcase_list"]['our_offers'],
                        "#" . $this->getChildTileList()->getName()
                    );
                    $detailPage->addAdditionalLink($link);
                }
                $conf->addDetailPage($detailPage, $this->getDetailFields($detailData), $detailData);
                $relatedShowcaseData = $this->getRelatedShowcaseData($detailData, $request);
                $relatedShowcaseTileList = $this->createRelatedShowcaseTileList();
                $relatedShowcaseFields = $this->getRelatedShowcaseTileFields();
                $conf->addTileList($this->getChildTileList(), $this->getChildTileFields(), $childData);
                $conf->addTileList($relatedShowcaseTileList, $relatedShowcaseFields, $relatedShowcaseData);
                $conf->setLanguage($objPage->language);
                $jsonConf = json_encode($conf);
                if ($jsonConf === false) {
                    // error encoding
                    C4gLogModel::addLogEntry("operator", json_last_error_msg());
                    $template->configuration = [];
                } else {
                    $template->configuration = $jsonConf;
                }
                $sc = new SearchConfiguration();
                $sc->addData($detailData, ['name', 'description']);
            } else {
                throw new RedirectResponseException($redirectUrl);
            }
            if ($this->model->gutesio_data_render_searchHtml) {
                $sc = new SearchConfiguration();
                $sc->addData($detailData, ['name', 'description']);
            }
        } else {
            throw new RedirectResponseException($redirectUrl);
        }

        $template->entrypoint = 'entrypoint_' . $this->model->id;
        if ($this->model->gutesio_data_render_searchHtml) {
            $template->searchHTML = $sc->getHTML();
        }

        return $template->getResponse();
    }

    protected function getDetailPage()
    {
        $page = new DetailPage();
        $page->setHeadline($this->languageRefs['details']['headline']);
        $settings = GutesioOperatorSettingsModel::findSettings();
        $mapData = MapDataConfigurator::prepareMapData(
            ContentModel::findById($settings->detail_map),
            Database::getInstance(),
            ["profile" => $settings->detail_profile],
            false
        );
        MapsResourceLoader::loadResources(["router" => true], $mapData);

        //$mapData['width'] = "100%";
        //$mapData['height'] = "100%";
        $mapData['geopicker']['input_geo_x'] = "#geox";
        $mapData['geopicker']['input_geo_y'] = "#geoy";
        $page->setMapData($mapData);
        $page->setSections($this->getSections());
        $page->setShowAnchorMenu(true);
        $page->setMenuSectionIndex(3);

        return $page;
    }

    private function getSections()
    {
        /**
         * Section 1: Name und Logo
         * Section 2: Header-Bild
         * Section 3: Anchor menü
         * Section 4: Beschreibung und Bildergalerie
         * Section 5: Tags
         * Section 6: Kontakt und Karte
         * Section 7: Partnerlogos
         */
        $sections = [];
        $section = new DetailPageSection($this->languageRefs['sections']['name_logo'], true, "detail-view__section-name-logo", false);
        $section->setRowForEachField(true);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['sections']['header'], false, "detail-view__section-header", false);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['sections']['menu'], true, "detail-view__section-menu", false);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['sections']['gallery'], true, "detail-view__section-gallery", true);
        $sections[] = $section;

        $section = new DetailPageSection("", true, "detail-view__section-detaildata", false);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['sections']['tags'], true, "detail-view__section-tags", false);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['sections']['contact_map'], true, "detail-view__section-contact-map", true);
        $sections[] = $section;

        $section = new DetailPageSection($this->languageRefs['sections']['relatedShowcaseLogos'], true, "detail-view__section-related-showcase-logos", false);
        $sections[] = $section;

        return $sections;
    }

    protected function getDetailFields(array $detailData)
    {
        $fields = [];

        $field = new DetailHTMLField();
        $field->setName('description');
        $field->setSection(4);
        $field->setClass("detail-view__description");
        $fields[] = $field;

        $field = new DetailFancyboxImageGallery();
        $field->setName("imageGallery");
        $field->setClass("detail-view__image-gallery");
        $field->setSection(4);
        $fields[] = $field;

        $field = new DetailVideoPreviewField();
        $field->setName("videoPreview");
        $field->setClass("detail-view__video-preview");
        $field->setSection(5);
        $fields[] = $field;

        $field = new DetailFancyboxImageGallery();
        $field->setName("relatedShowcaseLogos");
        $field->setClass("relatedShowcaseLogos detail-view__logos");
        $field->setSection(6);
        $fields[] = $field;

        if (is_array($detailData['types']) && count($detailData['types']) > 1) {
            $typeFieldLabel = $GLOBALS['TL_LANG']["operator_showcase_list"]['typeSingular'];
        } else {
            $typeFieldLabel = $GLOBALS['TL_LANG']["operator_showcase_list"]['typePlural'];
        }

        $field = new DetailTextField();
        $field->setSection(6);
        $field->setName("displayType");
        $field->setClass("displayType detail-view__display-type");
        $field->setLabel($typeFieldLabel);
        $fields[] = $field;

        $typeFields = $this->getTypeFields($detailData['types'] && is_array($detailData['types']) ? $detailData['types'] : []);
        foreach ($typeFields as $typeField) {
            $typeField->setSection(6);
            $fields[] = $typeField;
        }

        $field = new DetailTagField();
        $field->setSection(6);
        $field->setName("tags");
        //$field->setLabel("Besonderheiten");
        $fields[] = $field;

        $contactField = new DetailContactField();
        $contactField->setSection(7);
        $contactField->setLabel($this->languageRefs['contact']);
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
        $field->setSection(7);
        $field->setClass("detail-view__map-wrapper");
        $field->setName('mapLocation');
        $field->setGeoxField('geox');
        $field->setGeoyField('geoy');
        $fields[] = $field;

        $field = new DetailLinkField();
        $field->setSection(7);
        $field->setName("facebook");
        $field->setLinkText($GLOBALS['TL_LANG']["operator_showcase_list"]['facebook']);
        $field->setClass("social-media-link c4g-icon-wrapper icon-facebook");
        $contactField->addSocialMediaField($field);

        $field = new DetailLinkField();
        $field->setSection(7);
        $field->setName("instagram");
        $field->setClass("social-media-link c4g-icon-wrapper icon-instagram");
        $field->setLinkText($GLOBALS['TL_LANG']["operator_showcase_list"]['instagram']);
        $contactField->addSocialMediaField($field);

        $field = new DetailLinkField();
        $field->setSection(7);
        $field->setName("twitter");
        $field->setClass("social-media-link c4g-icon-wrapper icon-twitter");
        $field->setLinkText($GLOBALS['TL_LANG']["operator_showcase_list"]['twitter']);
        $contactField->addSocialMediaField($field);

        $field = new DetailLinkField();
        $field->setSection(7);
        $field->setName("whatsapp");
        $field->setClass("social-media-link c4g-icon-wrapper icon-whatsapp");
        $field->setLinkText($GLOBALS['TL_LANG']["operator_showcase_list"]['whatsapp']);
        $contactField->addSocialMediaField($field);

        $field = new DetailLinkField();
        $field->setSection(7);
        $field->setName("youtubeChannelLink");
        $field->setClass("social-media-link c4g-icon-wrapper icon-youtube");
        $field->setLinkText($GLOBALS['TL_LANG']["operator_showcase_list"]['youtubeChannelLink']);
        $contactField->addSocialMediaField($field);

        $field = new DetailLinkField();
        $field->setSection(7);
        $field->setName("vimeoChannelLink");
        $field->setClass("social-media-link c4g-icon-wrapper icon-vimeo");
        $field->setLinkText($GLOBALS['TL_LANG']["operator_showcase_list"]['vimeoChannelLink']);
        $contactField->addSocialMediaField($field);

        $field = new DetailLinkField();
        $field->setSection(7);
        $field->setName("wikipediaLink");
        $field->setClass("wikipedia-link c4g-icon-wrapper icon-wikipedia");
        $field->setLinkText($GLOBALS['TL_LANG']["operator_showcase_list"]['wikipedia']);
        $contactField->addSocialMediaField($field);

        return $fields;
    }

    private function getTypeFields(array $typeOptions): array
    {
        $typeIds = [];
        foreach ($typeOptions as $type) {
            if ($type['value']) {
                $typeIds[] = $type['value'];
            }
        }
        $typeFields = [];
        if (count($typeIds) > 0) {
            $inString = "(" . implode(",", $typeIds) . ")";
            $stm = Database::getInstance()
                ->prepare("SELECT * FROM tl_gutesio_data_type WHERE `id` IN $inString");
            $arrTypes = $stm->execute()->fetchAllAssoc();
            $technicalKeys = [];

            foreach ($arrTypes as $type) {
                if ($type['technicalKey']) {
                    $arrTechnicalKeys = StringUtil::deserialize($type['technicalKey'], true);
                    if ($arrTechnicalKeys && is_array($arrTechnicalKeys) && count($arrTechnicalKeys) > 0) {
                        $typeFields = array_merge($typeFields, TypeDetailFieldGenerator::getFieldsForTypes($arrTechnicalKeys));
                    }
                }
            }
        }

        return FieldUtil::makeFieldArrayUnique($typeFields);
    }

    private function getDetailData(Request $request): array
    {
        $detailData = $this->showcaseService->loadByAlias($this->alias) ?: [];
        if (count($detailData) === 0) {
            return [];
        }
        $clientUuid = $this->checkCookieForClientUuid($request);
        if ($clientUuid !== null) {
            $db = Database::getInstance();
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

        //ToDo
        foreach ($detailData as $key => $detailDatum) {
            //hotfix
            if (strpos(strtoupper($key), 'LINK')) {
                $detailData[$key] = C4GUtils::addProtocolToLink($detailDatum);
            }
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

        $field = new TagTileField();
        $field->setName('tagLinks');
        $field->setWrapperClass("c4g-list-element__taglinks-wrapper");
        $field->setClass("c4g-list-element__taglinks");
        $field->setInnerClass("c4g-list-element__taglinks-image");
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
            $field->setConditionField('typeId');
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
        return [
            'product' => $productPageModel->getFrontendUrl(),
            'event' => $eventPageModel->getFrontendUrl(),
            'job' => $jobPageModel->getFrontendUrl(),
            'arrangement' => $arrangementPageModel->getFrontendUrl(),
            'service' => $servicePageModel->getFrontendUrl()
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
            tl_gutesio_data_child_type.type as typeId, tl_gutesio_data_child_type.name as typeName FROM tl_gutesio_data_child a ' . '
            LEFT JOIN tl_gutesio_data_child b ON a.parentChildId = b.uuid ' . '
            LEFT JOIN tl_gutesio_data_child c ON b.parentChildId = c.uuid ' . '
            LEFT JOIN tl_gutesio_data_child d ON c.parentChildId = d.uuid ' . '
            JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
            JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
            JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
            WHERE tl_gutesio_data_element.alias = ?'
            . ' AND a.published = 1 AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())'
        )->execute($this->alias)->fetchAllAssoc();

        foreach ($childRows as $key => $row) {
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

            switch ($row['typeId']) {
                case 'product':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $url = Controller::replaceInsertTags("{{link_url::" . $objSettings->productDetailPage . "}}");

                    break;
                case 'event':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $url = Controller::replaceInsertTags("{{link_url::" . $objSettings->eventDetailPage . "}}");
                    break;
                case 'job':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $url = Controller::replaceInsertTags("{{link_url::" . $objSettings->jobDetailPage . "}}");
                    break;
                case 'arrangement':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $url = Controller::replaceInsertTags("{{link_url::" . $objSettings->arrangementDetailPage . "}}");
                    break;
                case 'service':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $url = Controller::replaceInsertTags("{{link_url::" . $objSettings->serviceDetailPage . "}}");
                    break;
                default:
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $url = Controller::replaceInsertTags("{{link_url::" . $objSettings->showcaseDetailPage . "}}");
                    break;
            }

            if (C4GUtils::endsWith($url, '.html')) {
                $href = str_replace('.html', '/' . strtolower(str_replace(['{', '}'], '', $row['uuid'])) . '.html', $url);
            } else {
                $href = $url . '/' . strtolower(str_replace(['{', '}'], '', $row['uuid']));
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

            $row['tagLinks'] = $childRows[$key]['tagLinks'];


            $row['href'] = $row['foreignLink'] && $row['directLink'] ?: $href;

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
                            $stmt = $database->prepare(
                                'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                                'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
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
                            $stmt = $database->prepare(
                                'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                                'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                            $icon['linkLabel'] = 'Onlinereservierung';
                            break;
                        case 'tag_onlineshop':
                            $stmt = $database->prepare(
                                'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                                'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
                            $icon['linkHref'] = $stmt->execute(
                                $row['uuid'],
                                'onlineShopLink'
                            )->fetchAssoc()['tagFieldValue'];
                            $stmt = $database->prepare(
                                'SELECT tagFieldValue FROM tl_gutesio_data_child_tag_values ' .
                                'WHERE childId = ? AND tagFieldKey = ? ORDER BY id ASC');
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

            switch ($row['typeId']) {
                case 'product':
                    $productData = $database->prepare('SELECT ' . '
                        (CASE ' . '
                            WHEN a.price IS NOT NULL THEN a.price ' . '
                            WHEN b.price IS NOT NULL THEN b.price ' . '
                            WHEN c.price IS NOT NULL THEN c.price ' . '
                            WHEN d.price IS NOT NULL THEN d.price ' . '
                        ELSE NULL END) AS price, ' . '
                        (CASE ' . '
                            WHEN a.strikePrice IS NOT NULL THEN a.strikePrice ' . '
                            WHEN b.strikePrice IS NOT NULL THEN b.strikePrice ' . '
                            WHEN c.strikePrice IS NOT NULL THEN c.strikePrice ' . '
                            WHEN d.strikePrice IS NOT NULL THEN d.strikePrice ' . '
                        ELSE NULL END) AS strikePrice, ' . '
                        (CASE ' . '
                            WHEN a.discount IS NOT NULL THEN a.discount ' . '
                            WHEN b.discount IS NOT NULL THEN b.discount ' . '
                            WHEN c.discount IS NOT NULL THEN c.discount ' . '
                            WHEN d.discount IS NOT NULL THEN d.discount ' . '
                        ELSE NULL END) AS discount, ' . '
                        (CASE ' . '
                            WHEN a.color IS NOT NULL THEN a.color ' . '
                            WHEN b.color IS NOT NULL THEN b.color ' . '
                            WHEN c.color IS NOT NULL THEN c.color ' . '
                            WHEN d.color IS NOT NULL THEN d.color ' . '
                        ELSE NULL END) AS color, ' . '
                        (CASE ' . '
                            WHEN a.size IS NOT NULL THEN a.size ' . '
                            WHEN b.size IS NOT NULL THEN b.size ' . '
                            WHEN c.size IS NOT NULL THEN c.size ' . '
                            WHEN d.size IS NOT NULL THEN d.size ' . '
                        ELSE NULL END) AS size  ' . '
                        FROM tl_gutesio_data_child_product a ' . '
                        JOIN tl_gutesio_data_child ca ON a.childId = ca.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cb ON ca.parentChildId = cb.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_product b ON b.childId = cb.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cc ON cb.parentChildId = cc.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_product c ON c.childId = cc.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cd ON cc.parentChildId = cd.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_product d ON d.childId = cd.uuid ' . '
                        WHERE a.childId = ?')
                        ->execute($row['uuid'])->fetchAssoc();
                    if (!empty($productData)) {
                        $productData['rawPrice'] = $productData['price'];
                        if ($productData['strikePrice'] > 0 && $productData['strikePrice'] > $productData['price']) {
                            $productData['strikePrice'] =
                                number_format(
                                    $productData['strikePrice'] ?: 0,
                                    2,
                                    ',',
                                    ''
                                ) . ' €*';
                            if ($productData['priceStartingAt']) {
                                $productData['strikePrice'] =
                                    $GLOBALS['TL_LANG']['offer_list']['frontend']['startingAt'] .
                                    ' ' . $productData['strikePrice'];
                            }
                        } else {
                            unset($productData['strikePrice']);
                        }
                        if (!empty($productData['priceReplacer'])) {
                            $productData['price'] =
                                $GLOBALS['TL_LANG']['offer_list']['price_replacer_options'][$productData['priceReplacer']];
                        } elseif ((!$productData['price'])/* && !$productData['priceStartingAt']*/) {
                            $productData['price'] =
                                $GLOBALS['TL_LANG']['offer_list']['price_replacer_options']['free'];
                        } else {
                            $productData['price'] =
                                number_format(
                                    $productData['price'] ?: 0,
                                    2,
                                    ',',
                                    ''
                                ) . ' €';
                            if ($productData['price'] > 0) {
                                $productData['price'] .= '*';
                            }
                            if ($productData['priceStartingAt']) {
                                $productData['price'] =
                                    $GLOBALS['TL_LANG']['offer_list']['frontend']['startingAt'] .
                                    ' ' . $productData['price'];
                            }
                        }
                        $childRows[$key] = array_merge($row, $productData);
                    }
                    break;
                case 'event':
                    $eventData = $database->prepare('SELECT ' . '
                        (CASE ' . '
                            WHEN a.beginDate IS NOT NULL THEN a.beginDate ' . '
                            WHEN b.beginDate IS NOT NULL THEN b.beginDate ' . '
                            WHEN c.beginDate IS NOT NULL THEN c.beginDate ' . '
                            WHEN d.beginDate IS NOT NULL THEN d.beginDate ' . '
                        ELSE NULL END) AS beginDate, ' . '
                        (CASE ' . '
                            WHEN a.beginTime IS NOT NULL THEN a.beginTime ' . '
                            WHEN b.beginTime IS NOT NULL THEN b.beginTime ' . '
                            WHEN c.beginTime IS NOT NULL THEN c.beginTime ' . '
                            WHEN d.beginTime IS NOT NULL THEN d.beginTime ' . '
                        ELSE NULL END) AS beginTime, ' . '
                        (CASE ' . '
                            WHEN a.endDate IS NOT NULL THEN a.endDate ' . '
                            WHEN b.endDate IS NOT NULL THEN b.endDate ' . '
                            WHEN c.endDate IS NOT NULL THEN c.endDate ' . '
                            WHEN d.endDate IS NOT NULL THEN d.endDate ' . '
                        ELSE NULL END) AS discount, ' . '
                        (CASE ' . '
                            WHEN a.endTime IS NOT NULL THEN a.endTime ' . '
                            WHEN b.endTime IS NOT NULL THEN b.endTime ' . '
                            WHEN c.endTime IS NOT NULL THEN c.endTime ' . '
                            WHEN d.endTime IS NOT NULL THEN d.endTime ' . '
                        ELSE NULL END) AS endTime, ' . '
                        (CASE ' . '
                            WHEN a.locationElementId IS NOT NULL THEN a.locationElementId ' . '
                            WHEN b.locationElementId IS NOT NULL THEN b.locationElementId ' . '
                            WHEN c.locationElementId IS NOT NULL THEN c.locationElementId ' . '
                            WHEN d.locationElementId IS NOT NULL THEN d.locationElementId ' . '
                        ELSE NULL END) AS locationElementId ' . '
                        FROM tl_gutesio_data_child_event a ' . '
                        JOIN tl_gutesio_data_child ca ON a.childId = ca.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cb ON ca.parentChildId = cb.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_event b ON b.childId = cb.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cc ON cb.parentChildId = cc.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_event c ON c.childId = cc.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child cd ON cc.parentChildId = cd.uuid ' . '
                        LEFT JOIN tl_gutesio_data_child_event d ON d.childId = cd.uuid ' . '
                        WHERE a.childId = ?')
                        ->execute($row['uuid'])->fetchAssoc();
                    $eventData['beginTime'] = date('H:i', $eventData['beginTime']);
                    $eventData['endTime'] = date('H:i', $eventData['endTime']);
                    $elementModel = GutesioDataElementModel::findBy('uuid', $eventData['locationElementId']);
                    if ($elementModel !== null) {
                        $eventData['locationElementId'] = $elementModel->name;
                    }
                    if (!empty($eventData)) {
                        $childRows[$key] = array_merge($row, $eventData);
                    }
                    break;
                default:
                    $childRows[$key] = $row;
                    break;
            }

            $childRows[$key]['href'] = strtolower(str_replace(['{', '}'], '', $row['uuid']));
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