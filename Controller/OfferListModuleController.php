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
use con4gis\FrameworkBundle\Classes\FormButtons\FilterButton;
use con4gis\FrameworkBundle\Classes\FormFields\DateRangeField;
use con4gis\FrameworkBundle\Classes\FormFields\HiddenFormField;
use con4gis\FrameworkBundle\Classes\FormFields\MultiCheckboxWithImageLabelFormField;
use con4gis\FrameworkBundle\Classes\FormFields\RadioGroupFormField;
use con4gis\FrameworkBundle\Classes\FormFields\RequestTokenFormField;
use con4gis\FrameworkBundle\Classes\FormFields\SelectFormField;
use con4gis\FrameworkBundle\Classes\FormFields\TextAreaFormField;
use con4gis\FrameworkBundle\Classes\FormFields\TextFormField;
use con4gis\FrameworkBundle\Classes\Forms\Form;
use con4gis\FrameworkBundle\Classes\Forms\ToggleableForm;
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\SearchConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ModalButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TagTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TextTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TileField;
use con4gis\FrameworkBundle\Classes\TileFields\WrapperTileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use con4gis\FrameworkBundle\Classes\Utility\RegularExpression;
use con4gis\FrameworkBundle\Traits\AutoItemTrait;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OfferListModuleController extends \Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController
{
    use AutoItemTrait;

    protected $model = null;
    protected $request = null;

    protected $tileList = null;
    
    const CC_FORM_SUBMIT_URL = '/showcase_child_cc_form_submit.php';
    const COOKIE_WISHLIST = "clientUuid";

    /**
     * @var OfferLoaderService
     */
    private $offerService = null;

    private $languageRefs = [];

    /**
     * OfferListModuleController constructor.
     * @param OfferLoaderService|null $offerService
     */
    public function __construct(ContaoFramework $framework, ?OfferLoaderService $offerService)
    {
        $framework->initialize();
        $this->offerService = $offerService;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        global $objPage;
        $this->model = $model;
        $this->offerService->setModel($model);
        $this->setAlias();
        $pageUrl = $this->pageUrl;
        if ($this->alias !== '') {
            throw new RedirectResponseException($this->pageUrl);
        }
        $this->offerService->setPageUrl($pageUrl);
        $this->offerService->setRequest($request);
        $limit = (int) $model->gutesio_data_limit ?: 1;
        $max = (int) $model->gutesio_data_max_data;
        if ($max > 0 && $max < $limit) {
            $limit = $max;
        }
        $this->offerService->setLimit($limit);
        $this->request = $request;
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js?v=" . time(), ResourceLoader::BODY, "c4g-framework");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/js/c4g_all.js");
        $this->setupLanguage();
        ResourceLoader::loadCssResource("/bundles/con4gisframework/css/tiles.css");

        if ($this->model->gutesio_data_layoutType !== "plain") {
//        ResourceLoader::loadCssResource("/bundles/con4gisframework/css/modal.css");
            ResourceLoader::loadCssResource("/bundles/gutesiooperator/css/c4g_listing.css");
        }
        $search = "";
        $conf = $this->getListFrontendConfiguration($search, $this->model->gutesio_child_type);
        $conf->setLanguage($objPage->language);
        if ($this->model->gutesio_data_render_searchHtml) {
            $sc = new SearchConfiguration();
            $sc->addData($this->getSearchLinks(), ['link']);
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
        return $template->getResponse();
    }

    /**
     * @Route(
     *     "/gutesio/operator/showcase_child_list_data/{offset}",
     *     name="showcase_child_list_data_ajax",
     *     methods={"GET"},
     *     requirements={"offset"="\d+"}
     *     )
     * @param Request $request
     * @param $offset
     * @return JsonResponse
     */
    public function getFilteredListDataAjax(Request $request, $offset)
    {
        $this->setAlias();
        $search = (string)$request->query->get('search');
        $search = $this->cleanupSearchString($search);
        $tagIds = (array)$request->query->get('tags');
        $moduleId = $request->query->get('moduleId');
        $filterData = [
            'tagIds' => $tagIds,
            'filterFrom' => intval((string)$request->query->get('filterFrom')),
            'filterUntil' => intval((string)$request->query->get('filterUntil')),
            'sorting' => (string)$request->query->get('sorting')
        ];
        $module = ModuleModel::findByPk($moduleId);
        if ($module) {
            $max = (int) $module->gutesio_data_max_data;
            if ($max !== 0 && $offset >= $max) {
                return new JsonResponse([]);
            }

            $this->model = $module;
            $this->offerService->setModel($module);
            $this->offerService->setPageUrl($this->pageUrl);
            $this->offerService->setRequest($request);

            $limit = (int) $module->gutesio_data_limit ?: 1;
            if ($max === 0) {
                $this->offerService->setLimit($limit);
            } else if ($limit + $offset <= $max) {
                $this->offerService->setLimit($limit);
            } else {
                $this->offerService->setLimit($max - $offset);
            }
        } else {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }
        $type = $request->getSession()->get('gutesio_child_type', '');
        $this->get('contao.framework')->initialize(true);
        $results = $this->offerService->getListData($search, $offset, $type, $filterData);
        $clientUuid = $this->checkCookieForClientUuid($request);
        foreach ($results as $key => $row) {
            $types = [];
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
            foreach ($row['types'] as $type) {
                $types[] = $type['label'];
                $row['types'] = implode(', ', $types);
            }
            $results[$key] = $row;
        }


        return new JsonResponse($results);
    }

    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request->cookies->get(self::COOKIE_WISHLIST);
        return $clientUuidCookie === null ? null : $clientUuidCookie;
    }

    private function cleanupSearchString($search)
    {
        $search = C4GUtils::secure_ugc($search);
        $search = str_replace("+", "", $search);

        return $search;
    }

    /**
     * @Route(
     *     "/gutesio/operator/showcase_child_cc_form/{lang}/{alias}",
     *     name="showcase_child_cc_form",
     *     methods={"GET"}
     *     )
     * @param Request $request
     * @param string $lang
     * @param string $alias
     * @return JsonResponse
     */
    public function getClickCollectForm(Request $request, string $lang, string $alias): JsonResponse
    {
        System::loadLanguageFile('offer_list', $lang);
        $formFields = [];

        $comkey = C4GUtils::getKey(
            C4gSettingsModel::findSettings(),
            9
        );
        
        $uuid = $alias;
        if (C4GUtils::startsWith($uuid, '{') !== true) {
            $uuid = '{' . $uuid;
        }
        if (C4GUtils::endsWith($uuid, '}') !== true) {
            $uuid .= '}';
        }

        $field = new HiddenFormField();
        $field->setName('uuid');
        $field->setValue($uuid);
        $formFields[] = $field->getConfiguration();

        $field = new HiddenFormField();
        $field->setName('key');
        $field->setValue((string) $comkey);
        $formFields[] = $field->getConfiguration();

        $field = new HiddenFormField();
        $field->setName('lang');
        $field->setValue($lang);
        $formFields[] = $field->getConfiguration();

        $field = new TextFormField();
        $field->setName('email');
        $field->setLabel($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['email'][0]);
        $field->setDescription($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['email'][1]);
        $field->setRequired();
        $field->setPattern(RegularExpression::EMAIL);
        $formFields[] = $field->getConfiguration();

        $field = new TextFormField();
        $field->setName('name');
        $field->setLabel($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['name'][0]);
        $field->setDescription($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['name'][1]);
        $field->setRequired();
        $formFields[] = $field->getConfiguration();

        $field = new SelectFormField();
        $field->setName('earliest');
        $field->setLabel($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['earliest'][0]);
        $field->setDescription($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['earliest'][1]);
        $field->setRequired();
        $field->setOptions($this->getEarliestOptions());
        $formFields[] = $field->getConfiguration();

        $field = new TextAreaFormField();
        $field->setName('notes');
        $field->setLabel($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['notes'][0]);
        $field->setDescription($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['notes'][1]);
        $field->setMaxLength(10000);
        $formFields[] = $field->getConfiguration();


        return new JsonResponse([
            'formFields' => $formFields
        ]);
    }

    private function getEarliestOptions(): array
    {
        System::loadLanguageFile('gutesio_frontend', 'de');
        $options = [];
        foreach ($GLOBALS['TL_LANG']['gutesio_frontend']['cc']['earliest'] as $key => $value) {
            if ($key === 'afternoon' && (int)date('H') >= 12) {
                continue;
            }
            $options[] = [
                'value' => $key,
                'label' => $value
            ];
        }
        return $options;
    }

    private function setupLanguage()
    {
        System::loadLanguageFile("offer_list");
        System::loadLanguageFile("gutesio_frontend");
        $this->languageRefs = $GLOBALS['TL_LANG']['offer_list'];
    }

    private function getListFrontendConfiguration(string $search, $type)
    {
        $conf = new FrontendConfiguration('entrypoint_' . $this->model->id);
        $conf->addForm(
            $this->getForm(),
            $this->getFormFields(),
            $this->getFormButtons(),
            [
                'search' => $search,
                'moduleId' => $this->model->id,
                'filterFrom' => null,
                'filterUntil' => null,
                'sorting' => "random"
            ]
        );
        $fullTextData = $this->offerService->getListData($search, 0, $type, []);
        $conf->addTileList(
            $this->getFullTextTileList($search !== ''),
            $this->getFullTextTileFields(),
            $fullTextData
        );

        return $conf;
    }

    protected function getForm()
    {
        $form = new ToggleableForm(new Form());
        $form->setMethod('POST');
        $form->setContainerRow(true);
        $form->setToggleableBaseClass('c4g-listfilter');
        $form->setToggleableOnLabel($GLOBALS['TL_LANG']['offer_list']['filter']['close_filter']);
        $form->setToggleableOffLabel($GLOBALS['TL_LANG']['offer_list']['filter']['open_filter']);
        $form->setToggleableOnClass('react-c4g-listfilter-opened');
        $form->setHidden($this->model->gutesio_enable_filter !== '1');

        return $form;
    }

    protected function getFormFields()
    {
        $fields = [];

        $field = new RequestTokenFormField();
        $fields[] = $field;

        $useProductFilter = false;
        $useEventFilter = false;
        $filter = StringUtil::deserialize($this->model->gutesio_child_filter, true);
        foreach ($filter as $value) {
            if ($value === "price") {
                $useProductFilter = true;
            }
            if ($value === "range") {
                $useEventFilter = true;
            }
        }
        $field = new TextFormField();
        $field->setName('search');
        $field->setLabel($this->model->gutesio_child_search_label);
        $field->setPlaceholder($this->model->gutesio_child_search_placeholder);
        $field->setDescription($this->model->gutesio_child_search_description);
        $field->setWrappingDiv();
        $field->setWrappingDivClass("form-view__searchinput");

        if ($this->model->gutesio_enable_tag_filter) {
            if ($useEventFilter || $useProductFilter) {
                $field->setClassName("offer-filter__searchinput form-view__searchinput");
            } else {
                $field->setClassName("offer-filter__searchinput form-view__searchinput");
            }
        }

        $fields[] = $field;
        if ($useEventFilter) {
            $field = new DateRangeField();
            $field->setFromFieldname("filterFrom");
            $field->setUntilFieldname("filterUntil");
            $field->setFromPlaceholderText($this->languageRefs['filterFromPlaceholder']);
            $field->setUntilPlaceholderText($this->languageRefs['filterUntilPlaceholder']);
            $field->setHeadline($this->languageRefs['chooseDateRange']);
            $field->setHeadlineClass("form-view__period-title");
            $field->setClassName("offer-filter__period form-view__period");
            $field->setDescription($this->languageRefs['chooseDateRange_desc']);
            $fields[] = $field;
        }
        if ($useProductFilter) {
            $sortFilter = new RadioGroupFormField();
            $sortFilter->setName("sorting");
            $sortFilter->setOptions([
                'random' => $this->languageRefs['filter']['sorting']['random'],
                'price_asc' => $this->languageRefs['filter']['sorting']['price_asc'],
                'price_desc' => $this->languageRefs['filter']['sorting']['price_desc'],
            ]);
            $sortFilter->setClassName("offer-filter__ascend-descend form-view__ascend-descend");
            $sortFilter->setChecked("random");
            $sortFilter->setOptionsClass("c4g-form-check c4g-form-check-inline");
            $fields[] = $sortFilter;
        }

        if ($this->model->gutesio_enable_tag_filter) {
            $tagFilter = new MultiCheckboxWithImageLabelFormField();
            $tagFilter->setName("tags");
            $tagFilter->setClassName("form-view__tag-filter");
            $tagFilter->setOptions($this->getTagOptions());
            $tagFilter->setOptionClass("tag-filter-item offer tag-filter__filter-item");
            $fields[] = $tagFilter;
        }
        // module id field so the id gets transferred when loading data async
        $moduleId = new HiddenFormField();
        $moduleId->setName("moduleId");
        $moduleId->setValue($this->model->id);
        $fields[] = $moduleId;

        return $fields;
    }

    private function getTagOptions()
    {
        $optionData = [];
        $arrTagIds = StringUtil::deserialize($this->model->gutesio_tag_filter_selection, true);

        foreach ($arrTagIds as $arrTagId) {
            $strSelect = "SELECT * FROM tl_gutesio_data_tag WHERE published = 1 AND uuid = ? AND (validFrom = 0 OR validFrom >= UNIX_TIMESTAMP() AND (validUntil = 0 OR validUntil <= UNIX_TIMESTAMP()))";
            $tag = Database::getInstance()->prepare($strSelect)->execute($arrTagId)->fetchAssoc();
            if ($tag) {
                if ($tag["technicalKey"] === "tag_opening_hours") {
                    // TODO temporarily remove opening_hours tag since it needs to be handled differently
                    // TODO until then, it would break the filter if it exists as option
                    continue;
                }
                if ($tag['image']) {
                    $objImage = FilesModel::findByUuid(StringUtil::binToUuid($tag['image']));
                    $optionData[$tag['uuid']] = [
                        'src' => $objImage->path,
                        'alt' => $tag['name']
                    ];
                } else {
                    $optionData[$tag['uuid']] = [];
                }
            }
        }

        return $optionData;
    }

    protected function getFormButtons()
    {
        $buttons = [];

        $button = new FilterButton();
        $button->setClassName("c4g-btn c4g-btn-filter");
        $button->setTargetComponent("full-text-tiles");
        $button->setAsyncUrl("/gutesio/operator/showcase_child_list_data/{offset}");
        $button->setCaption($GLOBALS['TL_LANG']['con4gis']['framework']['frontend']['button']['filter']);
        $button->setOuterClass("c4g-btn-filter-wrapper");
        $buttons[] = $button;

        return $buttons;
    }

    protected function getFullTextTileList(bool $updated): TileList
    {
        $this->tileList = new TileList('full-text-tiles');
        $headline = StringUtil::deserialize($this->model->headline);
        $this->tileList->setHeadline($headline['value']);
        $this->tileList->setHeadlineLevel((int)str_replace("h", "", $headline['unit']));
        $this->tileList->setTileClassName("offer-tile");
        $this->tileList->setTextBeforeUpdate($this->model->gutesio_child_text_search);
        $this->tileList->setTextAfterUpdate($this->model->gutesio_child_text_no_results);
        $this->tileList->setAsyncUrl('/gutesio/operator/showcase_child_list_data/{offset}');
        $this->tileList->setLoadingText(' ');
        $this->tileList->setUpdated($updated);
        $layoutType = $this->model->gutesio_data_layoutType;
        $class = "offer-tiles";
        $class .= " c4g-" . $layoutType . "-outer";
        $this->tileList->setClassName($class);
        $this->tileList->setLayoutType($layoutType);
        $this->tileList->setLoadStep($this->offerService->getLimit());
        $this->tileList->setBottomLine($GLOBALS['TL_LANG']['offer_list']['frontend']['list']['taxInfo']);
        $this->tileList->setScrollThreshold(0.05);
        $this->tileList->setUniqueField("uuid");
        $this->tileList->setSetAsyncAfterFilter(true);
        $this->tileList->setCheckAsyncWhileUpdate(true);
        if ($this->model->gutesio_data_change_layout_filter) {
            $this->tileList->setClassAfterFilter("c4g-" . $this->model->gutesio_data_layout_filter . "-outer");
        }

        return $this->tileList;
    }

    protected function getFullTextTileFields(): array
    {
        $settings = C4gSettingsModel::findSettings();
        $fields = [];

        $field = new ImageTileField();
        $field->setWrapperClass("c4g-list-element__image-wrapper");
        $field->setClass("c4g-list-element__image");
        $field->setName('image');
        $fields[] = $field;

        $field = new HeadlineTileField();
        $field->setName('name');
        $field->setLevel($this->tileList->getHeadlineLevel() + 1);
        $field->setWrapperClass("c4g-list-element__name-wrapper");
        $field->setClass("c4g-list-element__name");
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('typeName');
        $field->setWrapperClass("c4g-list-element__typename-wrapper");
        $field->setClass("c4g-list-element__typename");
        $fields[] = $field;

        $field = new LinkTileField();
        $field->setName('elementName');
        $field->setWrapperClass("c4g-list-element__elementname-wrapper");
        $field->setClass("c4g-list-element__elementname-link");
        $field->setLinkTextName('elementName');
        $field->setHref("/elementLink");
        $field->setHrefName('elementLink');
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('shortDescription');
        $field->setWrapperClass("c4g-list-element__shortdescription-wrapper");
        $field->setClass("c4g-list-element__shortDescription");
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('strikePrice');
        $field->setWrapperClass("c4g-list-element__strikeprice-wrapper");
        $field->setClass("c4g-list-element__strikePrice");
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('price');
        $field->setWrapperClass("c4g-list-element__price-wrapper");
        $field->setClass("c4g-list-element__price");
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('beginDate');
        $field->setWrapperClass("c4g-list-element__begindate-wrapper");
        $field->setClass("c4g-list-element__beginDate");
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('beginTime');
        $field->setWrapperClass("c4g-list-element__begintime-wrapper");
        $field->setClass("c4g-list-element__beginTime");
        $fields[] = $field;

        $field = new TagTileField();
        $field->setName('tagLinks');
        $field->setWrapperClass("c4g-list-element__taglinks-wrapper");
        $field->setClass("c4g-list-element__taglinks");
        $field->setInnerClass("c4g-list-element__taglinks-image");
        $fields[] = $field;

        global $objPage;
        $field = new ModalButtonTileField();
        $field->setName('cc');
        $field->setWrapperClass("c4g-list-element__clickcollect-wrapper");
        $field->setClass("c4g-list-element__clickcollect-link");
        $field->setLabel($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['modal_button_label']);
        $field->setUrl('/gutesio/operator/showcase_child_cc_form/'.$objPage->language.'/uuid');
        $field->setUrlField('uuid');
        $field->setConfirmButtonText($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['confirm_button_text']);
        $field->setCloseButtonText($GLOBALS['TL_LANG']['offer_list']['frontend']['cc_form']['close_button_text']);
        $field->setSubmitUrl(rtrim($settings->con4gisIoUrl, '/').self::CC_FORM_SUBMIT_URL);
        $field->setCondition('clickCollect', '1');
        $field->setCondition('type', 'product');
        $field->setInnerFields([
            'image',
            'name',
            'typeName',
            'strikePrice',
            'price',
            'beginDate',
            'beginTime',
        ]);
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
        $field->setLinkText($this->languageRefs['frontend']['putOnWishlist']);
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
        $field->setLinkText($this->languageRefs['frontend']['removeFromWishlist']);
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
        
        return [
            'product' => $productPageModel->getFrontendUrl(),
            'event' => $eventPageModel->getFrontendUrl(),
            'job' => $jobPageModel->getFrontendUrl(),
            'arrangement' => $arrangementPageModel->getFrontendUrl(),
            'service' => $servicePageModel->getFrontendUrl()
        ];
    }

    protected function getSearchLinks()
    {
        $database = Database::getInstance();
        $result = $database->prepare('SELECT c.uuid as uuid, t.type as type FROM tl_gutesio_data_child c ' .
            'JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid ' .
            'where c.published = 1')->execute()->fetchAllAssoc();
        $links = [];
        foreach ($result as $row) {
            switch ($row['type']) {
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
                    continue 2;
            }
            $alias = strtolower(str_replace(['{', '}'], '', $row['uuid']));
            if (C4GUtils::endsWith($url, '.html')) {
                $href = str_replace('.html', '/' . $alias . '.html', $url);
            } else {
                $href = $url . '/' . $alias;
            }
            $links[] = [
                'link' => "<a href=\"$href\"></a>"
            ];
        }
        return $links;
    }

    public function onGetSearchablePages(array $pages, int $rootId = null, bool $isSitemap = false, string $language = null): array
    {
        $db = Database::getInstance();
        $result = $db->prepare('SELECT c.uuid as uuid, t.type as type FROM tl_gutesio_data_child c ' .
            'JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid ' .
            'where c.published = 1')->execute()->fetchAllAssoc();

        foreach ($result as $row) {
            switch ($row['type']) {
                case 'product':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $page = $objSettings->productDetailPage;
                    break;
                case 'event':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $page = $objSettings->eventDetailPage;
                    break;
                case 'job':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $page = $objSettings->jobDetailPage;
                    break;
                case 'arrangement':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $page = $objSettings->arrangementDetailPage;
                    break;
                case 'service':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $page = $objSettings->serviceDetailPage;
                    break;
                default:
                    continue 2;
            }
            $parents = PageModel::findParentsById($page);
            if (sizeof($parents) < 2 || (int)$parents[sizeof($parents) - 1]->id !== (int)$rootId) {
                continue;
            }
            $url = Controller::replaceInsertTags("{{link_url::" . $page . "}}");
            $alias = strtolower(str_replace(['{', '}'], '', $row['uuid']));
            if (C4GUtils::endsWith($url, '.html')) {
                $url = str_replace('.html', '/' . $alias . '.html', $url);
            } else {
                $url = $url . '/' . $alias;
            }
            $pages[] = Controller::replaceInsertTags("{{env::url}}") . '/' . $url;
        }

        return $pages;
    }
}