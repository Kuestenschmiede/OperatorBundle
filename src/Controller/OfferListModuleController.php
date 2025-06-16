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
use con4gis\FrameworkBundle\Classes\FormButtons\FilterButton;
use con4gis\FrameworkBundle\Classes\FormFields\DateRangeField;
use con4gis\FrameworkBundle\Classes\FormFields\HiddenFormField;
use con4gis\FrameworkBundle\Classes\FormFields\MultiCheckboxWithImageLabelFormField;
use con4gis\FrameworkBundle\Classes\FormFields\RadioGroupFormField;
use con4gis\FrameworkBundle\Classes\FormFields\RequestTokenFormField;
use con4gis\FrameworkBundle\Classes\FormFields\SelectFormField;
use con4gis\FrameworkBundle\Classes\FormFields\TextFormField;
use con4gis\FrameworkBundle\Classes\Forms\Form;
use con4gis\FrameworkBundle\Classes\Forms\ToggleableForm;
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\SearchConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TagTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TextTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TileField;
use con4gis\FrameworkBundle\Classes\TileFields\WrapperTileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use con4gis\FrameworkBundle\Traits\AutoItemTrait;
use Contao\Config;
use Contao\Controller;
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
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildTypeModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use gutesio\OperatorBundle\Classes\Services\ServerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OfferListModuleController extends AbstractFrontendModuleController
{
    use AutoItemTrait;

    private ?ModuleModel $model = null;
    private ?Request $request = null;
    private ?TileList $tileList = null;

    private array $languageRefs = [];
    private array $languageRefsFrontend = [];

    private $initialDateSort = false;

    public const COOKIE_WISHLIST = "clientUuid";

    /**
     * OfferListModuleController constructor.
     * @param ContaoFramework $framework
     * @param OfferLoaderService|null $offerService
     * @param ServerService $serverService
     */
    public function __construct(
        private ContaoFramework $framework,
        private ?OfferLoaderService $offerService,
        private ServerService $serverService,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        global $objPage;
        $this->model = $model;
        $this->offerService->setModel($model);
        $this->setAlias($request);
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
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js|async", ResourceLoader::JAVASCRIPT, "c4g-framework");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js|async", ResourceLoader::JAVASCRIPT, "c4g-all");
        $this->setupLanguage();
        ResourceLoader::loadCssResource("/bundles/con4gisframework/dist/css/tiles.min.css");

        if ($this->model->gutesio_data_layoutType !== "plain") {
            ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing.min.css");
        }
        $search = "";
        $conf = $this->getListFrontendConfiguration($search, $this->model->gutesio_child_type);
        $conf->setLanguage($objPage->language);
        if ($this->model->gutesio_data_render_searchHtml) {
            $sc = new SearchConfiguration();
            $sc->addData($this->getSearchLinks($model), ['link']);
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
     *      path="/gutesio/operator/showcase_child_list_data/{offset}",
     *      name="showcase_child_list_data_ajax",
     *      methods={"GET"},
     *      requirements={"offset": "\d+"}
     *  )
     * @param Request $request
     * @param $offset
     * @return JsonResponse
     */
    #[Route(
        path: '/gutesio/operator/showcase_child_list_data/{offset}',
        name: 'showcase_child_list_data_ajax',
        methods: ['GET'],
        requirements: ['offset' => '\d+']
    )]
    public function getFilteredListDataAjax(Request $request, $offset)
    {
        $this->framework->initialize(true);
        $this->setAlias($request);
        System::loadLanguageFile("offer_list", "de");
        $search = (string)$request->query->get('search');
        $search = $this->cleanupSearchString($search);
        $tagIds = (array)$request->query->get('tags');
        $requestTypeIds = $request->query->get("types") ?: null;
        $location = $request->query->get('location') ?: null;

        if ($requestTypeIds === "" || $requestTypeIds === null) {
            $requestTypeIds = [];
        } else {
            $requestTypeIds = explode(",", $requestTypeIds);
        }

        if (count($tagIds) === 1 && $tagIds[0] === "") {
            $tagIds = [];
        }
        $moduleId = $request->query->get('moduleId');

        $filterData = [
            'tagIds' => $tagIds,
            'categoryIds' => $requestTypeIds,
            'filterFrom' => $request->query->get('filterFrom') ? intval((string)$request->query->get('filterFrom')) : null,
            'filterUntil' => $request->query->get('filterUntil') ? intval((string)$request->query->get('filterUntil')) : null,
            'sorting' => (string)$request->query->get('sorting'),
            'location' => $location
        ];

        $module = ModuleModel::findByPk($moduleId);
        if ($module) {
            if (!$module->gutesio_enable_filter && $module->gutesio_child_sort_by_date) {
                $filterData['sorting'] = 'date';
            }

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

            if (key_exists('types', $row) && is_array($row['types'])) {
                foreach ($row['types'] as $type) {
                    $types[] = $type['label'];
                    $row['types'] = implode(', ', $types);
                }
            }

            $results[$key] = $row;
        }


        return new JsonResponse($results);
    }

    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request && $request->cookies ? $request->cookies->get(self::COOKIE_WISHLIST) : null;
        return $clientUuidCookie === null ? null : $clientUuidCookie;
    }

    private function cleanupSearchString($search)
    {
        $search = C4GUtils::secure_ugc($search);
        $search = str_replace("+", "", $search);

        return $search;
    }

    private function setupLanguage()
    {
        System::loadLanguageFile("offer_list");
        System::loadLanguageFile("gutesio_frontend");
        $this->languageRefs = $GLOBALS['TL_LANG']['offer_list'];
        $this->languageRefsFrontend = $GLOBALS['TL_LANG']['gutesio_frontend'];
    }

    private function getListFrontendConfiguration(string $search, $type)
    {
        $filterData = [
            'search' => $search,
            'moduleId' => $this->model->id,
            'filterFrom' => null,
            'filterUntil' => null,
            'sorting' => "random"
        ];
        if ($this->model->gutesio_child_sort_by_date) {
            if ($this->model->gutesio_child_data_mode === "1") {
//                $types = StringUtil::deserialize($this->model->gutesio_child_type, true);
                $this->initialDateSort = true;
            }

            $filterData['sorting'] = 'date';
        }
        
        $conf = new FrontendConfiguration('entrypoint_' . $this->model->id);

        if ($this->model->gutesio_enable_filter === '1') {
            $filterFields = $this->getFormFields();

            $queryParams = $this->request->query->all();
            if ($queryParams['types']) {

                // get type field
                $typeField = null;
                foreach ($filterFields as $filterField) {
                    if ($filterField->getName() === "types") {
                        $typeField = $filterField;
                        break;
                    }
                }

                if ($typeField !== null) {
                    $types = is_array($queryParams['types']) ? $queryParams['types'] : explode(",", $queryParams['types']);
                    $fieldOptions = $typeField->getOptions();

                    $initialOptions = [];
                    foreach ($fieldOptions as $option) {
                        if (in_array($option['value'], $types)) {
                            $initialOptions[] = $option;
                        }
                    }

                    $filterData['types'] = $initialOptions;
                }
            }
            if ($queryParams['postals']) {
                // TODO wie gehen wir mit mehreren PLZs/Wildcard PLZ um? Das alles hier ist damit ja nicht wirklich kompatibel
                $filterData['search'] = $queryParams['postals'];
            }
            if ($queryParams['filterFrom']) {
                $filterData['filterFrom'] = $queryParams['filterFrom'];
                $filterData['sorting'] = "date";
            }
            if ($queryParams['filterUntil']) {
                $filterData['filterUntil'] = $queryParams['filterUntil'];
                $filterData['sorting'] = "date";
            }

            $conf->addForm(
                $this->getForm(),
                $filterFields,
                $this->getFormButtons(),
                $filterData
            );
        } else {
            // add hidden form for the filter data
            $buttons = $this->getFormButtons();
            foreach ($buttons as $button) {
                $button->setClassName("hidden");
            }
            $conf->addForm(
                $this->getForm(),
                [],
                $buttons,
                $filterData
            );
        }

        $fullTextData = [];
        $conf->addTileList(
            $this->getFullTextTileList($search !== ''),
            $this->getFullTextTileFields(),
            $fullTextData
        );

        return $conf;
    }

    protected function getForm()
    {
        if ($this->model->gutesio_enable_ext_filter === '1') {
            $form = new ToggleableForm(new Form());
            $form->setMethod('POST');
            $form->setContainerRow(true);
            $form->setToggleableBaseClass('c4g-listfilter');
            $form->setToggleableOnLabel($GLOBALS['TL_LANG']['offer_list']['filter']['close_filter']);
            $form->setToggleableOffLabel($GLOBALS['TL_LANG']['offer_list']['filter']['open_filter']);
            $form->setToggleableOnClass('react-c4g-listfilter-opened');
            $form->setHidden($this->model->gutesio_enable_filter !== '1');
        } else {
            $form = new Form();
            $form->setMethod('POST');
            $form->setContainerRow(true);
            $form->setClass('c4g-listfilter-default');
//            $form->setToggleableOnLabel($GLOBALS['TL_LANG']['offer_list']['filter']['close_filter']);
//            $form->setToggleableOffLabel($GLOBALS['TL_LANG']['offer_list']['filter']['open_filter']);
//            $form->setToggleableOnClass('react-c4g-listfilter-opened');
            $form->setHidden($this->model->gutesio_enable_filter !== '1');
        }

        return $form;
    }

    private function getCategoryOptions($selectedTypes = [], $selectedOfferTypes = [])
    {
        $database = Database::getInstance();
        if ((is_array($selectedOfferTypes) && count($selectedOfferTypes) > 0) && !(is_array($selectedTypes) && count($selectedTypes) > 0)) {
            $typeStr = implode("','", $selectedOfferTypes);
            $sql = "SELECT DISTINCT tl_gutesio_data_child_type.uuid AS uuid, tl_gutesio_data_child_type.name AS name, tl_gutesio_data_child_type.type AS type FROM tl_gutesio_data_child_type"
                . " WHERE type IN ('" . $typeStr . "') ORDER BY name ASC";
            $arrTypes = $database->prepare($sql)->execute()->fetchAllAssoc();
        } else if (is_array($selectedTypes) && count($selectedTypes) > 0) {
            $typeStr = implode("','", $selectedTypes);
            $sql = "SELECT DISTINCT tl_gutesio_data_child_type.uuid AS uuid, tl_gutesio_data_child_type.name AS name FROM tl_gutesio_data_child_type"
                . " WHERE uuid IN ('" . $typeStr . "') ORDER BY name ASC";
            $arrTypes = $database->prepare($sql)->execute()->fetchAllAssoc();
        } else {
            $arrTypes = GutesioDataChildTypeModel::findAll();
            $arrTypes = $arrTypes ? $arrTypes->fetchAll() : [];
        }

        $options = [];
        foreach ($arrTypes as $type) {
            $options[] = ['value' => $type['uuid'],'label' => $type['name']];
        }

        return $options;
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
        $field->setLabel(str_replace('&#39;', "'", $this->model->gutesio_child_search_label) ?: '');
        $field->setPlaceholder(str_replace('&#39;', "'", $this->model->gutesio_child_search_placeholder));
        $field->setDescription(str_replace('&#39;', "'", $this->model->gutesio_child_search_description));
        $field->setWrappingDiv();
        $field->setWrappingDivClass("form-view__searchinput");
        $field->setCache(true); //ToDo module switch
        $field->setEntryPoint($this->model->id);

        if ($this->model->gutesio_enable_tag_filter) {
            if ($useEventFilter || $useProductFilter) {
                $field->setClassName("offer-filter__searchinput form-view__searchinput");
            } else {
                $field->setClassName("offer-filter__searchinput form-view__searchinput");
            }
        }

        $fields[] = $field;

        if ($this->model->gutesio_enable_location_filter) {
            $locationFilter = new TextFormField();
            $locationFilter->setName("location");
            $locationFilter->setLabel($this->languageRefsFrontend['filter']['locationfilter']['label'] ?: '');
            $locationFilter->setClassName("form-view__location-filter");
            $locationFilter->setPlaceholder("PLZ oder Ort eingeben");
            $locationFilter->setCache(true);
            $locationFilter->setEntryPoint($this->model->id);
            $fields[] = $locationFilter;
        }

        if ($this->model->gutesio_enable_category_filter) {
            $selectedTypes = unserialize($this->model->gutesio_category_filter_selection);
            $selectedOfferTypes = [];

            if ($this->model->gutesio_child_data_mode === "1") { //type
                $selectedOfferTypes = $this->model ? StringUtil::deserialize($this->model->gutesio_child_type, true) : [];
            } else if ($this->model->gutesio_child_data_mode === "2") { //category
                $loadThisTypes  = $this->model ? StringUtil::deserialize($this->model->gutesio_child_category, true) : [];
                if ($selectedTypes) {
                    $selectedTypes = array_intersect($selectedTypes, $loadThisTypes);
                    $selectedTypes = array_values($selectedTypes);
                } else {
                    $selectedTypes = $loadThisTypes;
                }

            }

            $types = $this->getCategoryOptions($selectedTypes, $selectedOfferTypes);
            $typeField = new SelectFormField();
            $typeField->setName("types");
            $typeField->setLabel($this->languageRefsFrontend['filter']['typefilter']['label']);
            $typeField->setClassName("form-view__type-filter");
            $typeField->setPlaceholder("Kategorie auswählen");
            $typeField->setOptions($types);
            $typeField->setMultiple(true);
            $typeField->setCache(true); //ToDo module switch
            $typeField->setEntryPoint($this->model->id);
            $fields[] = $typeField;
        }



        $sortFilter = new RadioGroupFormField();
        $sortFilter->setName("sorting");
        $sortFilter->setLabel($this->languageRefsFrontend['filter']['sorting']['label']);

        if ($useEventFilter) {
            $field = new DateRangeField();
            $field->setFromFieldname("filterFrom");
            $field->setUntilFieldname("filterUntil");
            $field->setFromPlaceholderText($this->languageRefs['filterFromPlaceholder']);
            $field->setUntilPlaceholderText($this->languageRefs['filterUntilPlaceholder']);
            $field->setHeadline($this->languageRefs['chooseDateRange']);
            $field->setHeadlineClass("form-view__period-title");
            $field->setClassName("offer-filter__period form-view__period");
            //$field->setDescription($this->languageRefs['chooseDateRange_desc']);
            $field->setCache(false); //ToDo module switch
            $field->setEntryPoint($this->model->id);
            $fields[] = $field;
        }

        if (!$this->model->gutesio_disable_sorting_filter) {
            if ($useEventFilter && !$useProductFilter) {
                $sortFilterOptions = [
                    'date' => $this->languageRefs['filter']['sorting']['date_asc'],
                    'name_asc' => $this->languageRefs['filter']['sorting']['name_asc'],
                    'name_desc' => $this->languageRefs['filter']['sorting']['name_desc'],
                    'random' => $this->languageRefs['filter']['sorting']['random'],
                ];
            } else if ($useEventFilter) {
                $sortFilterOptions = [
                    'random' => $this->languageRefs['filter']['sorting']['random'],
                    'price_asc' => $this->languageRefs['filter']['sorting']['price_asc'],
                    'price_desc' => $this->languageRefs['filter']['sorting']['price_desc'],
                    'name_asc' => $this->languageRefs['filter']['sorting']['name_asc'],
                    'name_desc' => $this->languageRefs['filter']['sorting']['name_desc'],
                    'date' => $this->languageRefs['filter']['sorting']['date_asc'],
                ];
            } else if ($useProductFilter) {
                $sortFilterOptions = [
                    'random' => $this->languageRefs['filter']['sorting']['random'],
                    'price_asc' => $this->languageRefs['filter']['sorting']['price_asc'],
                    'price_desc' => $this->languageRefs['filter']['sorting']['price_desc'],
                    'name_asc' => $this->languageRefs['filter']['sorting']['name_asc'],
                    'name_desc' => $this->languageRefs['filter']['sorting']['name_desc'],
                ];
            } else {
                $sortFilterOptions = [
                    'random' => $this->languageRefs['filter']['sorting']['random'],
                    'name_asc' => $this->languageRefs['filter']['sorting']['name_asc'],
                    'name_desc' => $this->languageRefs['filter']['sorting']['name_desc'],
                ];
            }

            $sortFilterOptions['tstmp_desc'] = $this->languageRefs['filter']['sorting']['tstmp_desc'];
            $sortFilter->setOptions($sortFilterOptions);

            if ($this->initialDateSort) {
                $sortFilter->setChecked("date");
            } else {
                $sortFilter->setChecked("random");
            }

            $sortFilter->setClassName("offer-filter__ascend-descend form-view__ascend-descend");
            $sortFilter->setOptionsClass("c4g-form-check c4g-form-check-inline");
            $sortFilter->setCache(true); //ToDo module switch
            $sortFilter->setEntryPoint($this->model->id);
            $fields[] = $sortFilter;
        }

        if ($this->model->gutesio_enable_tag_filter) {
            $tagFilter = new MultiCheckboxWithImageLabelFormField();
            $tagFilter->setName("tags");
            $tagFilter->setLabel('');//$this->languageRefsFrontend['filter']['tagfilter']['label']);
            $tagFilter->setClassName("form-view__tag-filter");
            $tagFilter->setOptions($this->getTagOptions());
            $tagFilter->setOptionClass("tag-filter-item offer tag-filter__filter-item");
            $tagFilter->setCache(true); //ToDo module switch
            $tagFilter->setEntryPoint($this->model->id);
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

        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;
        $fileUtils = new FileUtils();

        foreach ($arrTagIds as $arrTagId) {
            $strSelect = "SELECT * FROM tl_gutesio_data_tag WHERE published = 1 AND uuid = ? AND (validFrom IS NULL OR validFrom = 0 OR validFrom <= UNIX_TIMESTAMP() AND (validUntil IS NULL OR validUntil = 0 OR validUntil >= UNIX_TIMESTAMP()))";
            $tag = Database::getInstance()->prepare($strSelect)->execute($arrTagId)->fetchAssoc();
            if ($tag) {
                if ($tag["technicalKey"] === "tag_opening_hours") {
                    // TODO temporarily remove opening_hours tag since it needs to be handled differently
                    // TODO until then, it would break the filter if it exists as option
                    continue;
                }
                if ($tag['imageCDN']) {
                    //$objImage = FilesModel::findByUuid(StringUtil::binToUuid($tag['image']));
                    $imageFile = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$tag['imageCDN']);
                    foreach ($optionData as $key=>$option) {
                        if (($option['alt'] == $tag['name']) || ($option['src'] == $imageFile)) {
                            continue(2);
                        }
                    }

                    $optionData[$tag['uuid']] = [
                        'src' => $imageFile,
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
        $button->setCaption($this->languageRefs['filter']['apply_filter'] ?: 'Suchen');
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
        $this->tileList->setOnlySearchWithParam("moduleId");

        return $this->tileList;
    }

    protected function getFullTextTileFields(): array
    {
        $user = FrontendUser::getInstance();
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
            $field->setCheckOrientation($this->model->gutesio_data_layoutType !== "plain");
            $fields[] = $field;
            //break;
        }

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
        $field->setGenerateValueClasses(true);
        $fields[] = $field;

        $field = new LinkTileField();
        $field->setName('elementName');
        $field->setWrapperClass("c4g-list-element__elementname-wrapper");
        $field->setClass("c4g-list-element__elementname-link");
        $field->setLinkTextName('elementName');
        $field->setHref("elementLink");
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
        $field->setName('beginDateDisplay');
        $field->setWrapperClass("c4g-list-element__begindate-wrapper");
        $field->setClass("c4g-list-element__beginDate");
        $field->addCondition(new FieldNotValueCondition('beginDateDisplay', '0'));
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('beginTimeDisplay');
        $field->setWrapperClass("c4g-list-element__begintime-wrapper");
        $field->setClass("c4g-list-element__beginTime");
        $field->addCondition(new FieldNotValueCondition('beginTimeDisplay', '0'));
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('endDateDisplay');
        $field->setWrapperClass("c4g-list-element__enddate-wrapper");
        $field->setClass("c4g-list-element__endDate");
        $field->addCondition(new FieldNotValueCondition('endDateDisplay', '0'));
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('endTimeDisplay');
        $field->setWrapperClass("c4g-list-element__endtime-wrapper");
        $field->setClass("c4g-list-element__endTime");
        $field->addCondition(new FieldNotValueCondition('endTimeDisplay', '0'));
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('maxCredit');
        $field->setFormat($GLOBALS['TL_LANG']['offer_list']['maxCredit_format']);
        $field->setWrapperClass("c4g-list-element__maxcredit-wrapper");
        $field->setClass("c4g-list-element__maxCredit");
        $field->addCondition(new FieldNotValueCondition('maxCredit', '0'));
        $field->addCondition(new FieldValueCondition('customizableCredit', '1'));
        $fields[] = $field;

        $field = new TextTileField();
        $field->setName('credit');
        $field->setFormat($GLOBALS['TL_LANG']['offer_list']['credit_format']);
        $field->setWrapperClass("c4g-list-element__credit-wrapper");
        $field->setClass("c4g-list-element__credit");
        $field->addCondition(new FieldNotValueCondition('credit', '0'));
        $field->addCondition(new FieldNotValueCondition('customizableCredit', '1'));
        $fields[] = $field;

        $field = new TagTileField();
        $field->setName('tagLinks');
        $field->setWrapperClass("c4g-list-element__taglinks-wrapper");
        $field->setClass("c4g-list-element__taglinks");
        $field->setInnerClass("c4g-list-element__taglinks-image");
        $field->setLinkField("linkHref");
        $fields[] = $field;
    
        $field = new WrapperTileField();
        $field->setWrappedFields(['uuid', 'href', 'cart-link']);
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
        $field->addCondition(new FieldNotValueCondition('ownerMemberId', (string) $user->id));
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
        $field->addCondition(new FieldNotValueCondition('ownerMemberId', (string) $user->id));
        $field->setAddDataAttributes(true);
        $field->setHookAfterClick(true);
        $field->setHookName("removeFromWishlist");
        $fields[] = $field;


        $page = $this->model->cart_page ?: 0;
        if ($page !== 0) {
            $page = PageModel::findByPk($page);
            if ($page) {
                $purchasableOfferTypeCondition = new OrCondition();
                $purchasableOfferTypeCondition->addConditions(
                    new FieldValueCondition('type', 'product'),
                    new FieldValueCondition('type', 'voucher')
                );
                $field = new LinkButtonTileField();
                $field->setName("uuid");
                $field->setWrapperClass("c4g-list-element__cart-wrapper");
                $field->setClass("c4g-list-element__cart-link put-in-cart");
                $field->setHref($this->serverService->getMainServerURL() . "/gutesio/main/cart/add");
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

        return [
            'product' => $objSettings->productDetailPage ? $this->urlGenerator->generate("tl_page." . $objSettings->productDetailPage, ['parameters' => ""]) : "",
            'event' => $objSettings->eventDetailPage ? $this->urlGenerator->generate("tl_page." . $objSettings->eventDetailPage, ['parameters' => ""]) : "",
            'job' => $objSettings->jobDetailPage ? $this->urlGenerator->generate("tl_page." . $objSettings->jobDetailPage, ['parameters' => ""]) : "",
            'arrangement' => $objSettings->arrangementDetailPage ? $this->urlGenerator->generate("tl_page." . $objSettings->arrangementDetailPage, ['parameters' => ""]) : "",
            'service' => $objSettings->serviceDetailPage ? $this->urlGenerator->generate("tl_page." . $objSettings->serviceDetailPage, ['parameters' => ""]) : "",
            'person' => $objSettings->personDetailPage ? $this->urlGenerator->generate("tl_page." . $objSettings->personDetailPage, ['parameters' => ""]) : "",
            'voucher' => $objSettings->voucherDetailPage ? $this->urlGenerator->generate("tl_page." . $objSettings->voucherDetailPage, ['parameters' => ""]) : "",
        ];
    }

    protected function getSearchLinks($model)
    {
        $database = Database::getInstance();
        $result = $database->prepare('SELECT c.uuid as uuid, t.type as type FROM tl_gutesio_data_child c ' .
            'JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid ' .
            'where c.published = 1')->execute()->fetchAllAssoc();
        $links = [];

        $childTypes = $model ? StringUtil::deserialize($model->gutesio_child_type, true) : [];

        foreach ($result as $row) {
            switch ($row['type']) {
                case 'product':
                    if (!count($childTypes) || in_array($row['type'], $childTypes)) {
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $url = C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->productDetailPage . "}}");
                    }
                    break;
                case 'event':
                    if (!count($childTypes) || in_array($row['type'], $childTypes)) {
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $url = C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->eventDetailPage . "}}");
                    }
                    break;
                case 'job':
                    if (!count($childTypes) || in_array($row['type'], $childTypes)) {
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $url = C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->jobDetailPage . "}}");
                    }
                    break;
                case 'arrangement':
                    if (!count($childTypes) || in_array($row['type'], $childTypes)) {
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $url = C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->arrangementDetailPage . "}}");
                    }
                    break;
                case 'service':
                    if (!count($childTypes) || in_array($row['type'], $childTypes)) {
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $url = C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->serviceDetailPage . "}}");
                    }
                    break;
                case 'person':
                    if (!count($childTypes) || in_array($row['type'], $childTypes)) {
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $url = C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->personDetailPage . "}}");
                    }
                    break;
                case 'voucher':
                    if (!count($childTypes) || in_array($row['type'], $childTypes)) {
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $url = C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->voucherDetailPage . "}}");
                    }
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

    /**
     * just needed with Contao 4.9
     *
     * @param array $pages
     * @param int|null $rootId
     * @param bool $isSitemap
     * @param string|null $language
     * @return array
     */
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
                case 'person':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $page = $objSettings->personDetailPage;
                    break;
                case 'voucher':
                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                    $page = $objSettings->voucherDetailPage;
                    break;
                default:
                    continue 2;
            }
            $parents = PageModel::findParentsById($page);
            if ($parents === null || count($parents) < 2 || (int)$parents[count($parents) - 1]->id !== (int)$rootId) {
                continue;
            }
            $url = Controller::replaceInsertTags("{{link_url::" . $page . "}}");
            $alias = strtolower(str_replace(['{', '}'], '', $row['uuid']));
            if (C4GUtils::endsWith($url, '.html')) {
                $url = str_replace('.html', '/' . $alias . '.html', $url);
            } else {
                $url = $url . '/' . $alias;
            }
            $pages[] = C4GUtils::replaceInsertTags("{{env::url}}") . '/' . $url;
        }

        return $pages;
    }
}