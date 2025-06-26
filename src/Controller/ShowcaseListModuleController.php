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
use con4gis\FrameworkBundle\Classes\FormButtons\FilterButton;
use con4gis\FrameworkBundle\Classes\FormFields\HiddenFormField;
use con4gis\FrameworkBundle\Classes\FormFields\MultiCheckboxWithImageLabelFormField;
use con4gis\FrameworkBundle\Classes\FormFields\RadioGroupFormField;
use con4gis\FrameworkBundle\Classes\FormFields\SelectFormField;
use con4gis\FrameworkBundle\Classes\FormFields\TextFormField;
use con4gis\FrameworkBundle\Classes\Forms\Form;
use con4gis\FrameworkBundle\Classes\Forms\ToggleableForm;
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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Database;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\ShowcaseService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShowcaseListModuleController extends \Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController
{
    use AutoItemTrait;

    private ?TileList $tileList = null;
    private array $tileItems = [];
    private ?ModuleModel $model = null;

    private array $languageRefs = [];
    private array $languageRefsFrontend = [];


    public const AJAX_GET_ROUTE = '/gutesio/operator/showcase_tile_list_data/{offset}';
    public const FILTER_ROUTE = '/gutesio/operator/showcase_tile_list/filter';
    public const TYPE = 'showcase_list_module';
    public const COOKIE_WISHLIST = "clientUuid";

    public function __construct(private ShowcaseService $showcaseService, private ContaoFramework $framework, private UrlGeneratorInterface $urlGenerator, private InsertTagParser $parser)
    {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        global $objPage;
        $this->model = $model;
        $this->setAlias($request);
        $redirectPage = $model->gutesio_data_redirect_page;
        $redirectUrl = $this->parser->replace("{{link_url::$redirectPage}}");
        //$redirectUrl = $this->urlGenerator->generate("tl_page." . $redirectPage, ['alias' => 'alias']);
        if ($redirectPage && $redirectUrl) {
            if (!C4GUtils::endsWith($this->pageUrl, $redirectUrl)) {
                $this->pageUrl = $redirectUrl;
            }
        }
        if ($this->alias !== "") {
            return new Response();
        }
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js|async", ResourceLoader::JAVASCRIPT, "c4g-framework");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js|async", ResourceLoader::JAVASCRIPT, "c4g-all");
        System::loadLanguageFile("operator_showcase_list");
        System::loadLanguageFile("gutesio_frontend");
        $this->languageRefs = $GLOBALS['TL_LANG']["operator_showcase_list"];
        $this->languageRefsFrontend = $GLOBALS['TL_LANG']['gutesio_frontend'];

        $tileList = $this->getTileList();
        $fields = $this->getFields();
        $data = $this->getInitialData();
        $conf = new FrontendConfiguration('entrypoint_' . $this->model->id);
        $conf->setLanguage($objPage->language);
        
        $arrFilter = $this->buildFilter();
        $conf->addForm(
            $arrFilter['form'],
            $arrFilter['fields'],
            $arrFilter['buttons'],
            [
                'randKey' => $data['randKey'],
                'moduleId' => $this->model->id,
                'tags' => [],
                'sorting' => $model->gutesio_initial_sorting ?: "random"
            ]
        );

        unset($data['randKey']);

        $conf->addTileList($tileList, $fields, $data);
        $jsonConf = json_encode($conf);
        if ($jsonConf === false) {
            C4gLogModel::addLogEntry("operator", json_last_error_msg());
            $template->configuration = [];
        } else {
            $template->configuration = $jsonConf;
        }
        ResourceLoader::loadCssResource("/bundles/con4gisframework/dist/css/tiles.min.css");

        if ($this->model->gutesio_data_layoutType !== "plain") {
            ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing.min.css");
        }

        $template->entrypoint = 'entrypoint_' . $this->model->id;

        if ($this->model->gutesio_data_render_searchHtml) {

            $requestUserAgent = $request->headers->get("User-Agent");
            // only render content when it's the Googlebot
            if (str_contains($requestUserAgent, "Googlebot")) {
                $elements = $this->getAllData($this->model);

                if ($elements && is_array($elements) && is_array($elements[0]) && $this->model->gutesio_enable_filter) {
                    $sc = new SearchConfiguration();
                    $sc->addData($this->getSearchLinks($elements), ['link']);
                    $template->searchHTML = $sc->getHTML();
                    $template->itemListElement = $this->getMetaData($elements);
                }
            }
        }


        return $template->getResponse();
    }

    protected function getTileList(): TileList
    {
        $tileList = new TileList('tiles');
        $headline = StringUtil::deserialize($this->model->headline, true);
        $tileList->setHeadline((string)$headline['value']);
        $tileList->setHeadlineLevel((int)str_replace("h", "", $headline['unit']));
        $tileList->setAsyncUrl(self::AJAX_GET_ROUTE);
        $tileList->setTileClassName("showcase-tile");
        $layoutType = $this->model->gutesio_data_layoutType;
        $class = "showcase-tiles";
        $class .= " c4g-" . $layoutType . "-outer";
        $tileList->setClassName($class);
        $tileList->setLayoutType($layoutType);
        $tileList->setLoadStep($this->model->gutesio_data_limit);
        $tileList->setLoadingText(" ");
        $tileList->setTextAfterUpdate($this->languageRefs['no_results']); //ToDo
        $tileList->setUniqueField("uuid");
        $tileList->setScrollThreshold(0.1);
        $tileList->setSetAsyncAfterFilter(true);
        $tileList->setCheckAsyncWhileUpdate(true);
        $tileList->setConditionalTileClassName('interregional');
        $tileList->setConditionalTileClassField('releaseType');
        $tileList->setConditionalTileClassValue('interregional');
        if ($this->model->gutesio_data_change_layout_filter) {
            $tileList->setClassAfterFilter("c4g-" . $this->model->gutesio_data_layout_filter . "-outer");
        }

        return $tileList;
    }

    /**
     * @Route(
     *      path="/gutesio/operator/showcase_tile_list_data/{offset}",
     *      name="showcase_tile_list_data",
     *      methods={"GET"},
     *      requirements={"offset": "\d+"}
     *  )
     * @param Request $request
     * @param $offset
     * @return JsonResponse
     */
    #[Route(
        path: '/gutesio/operator/showcase_tile_list_data/{offset}',
        name: 'showcase_tile_list_data',
        methods: ['GET'],
        requirements: ['offset' => '\d+']
    )]
    public function getDataAction(Request $request, int $offset)
    {
        $this->framework->initialize(true);
        System::loadLanguageFile("field_translations", "de");
        System::loadLanguageFile("operator_showcase_list", "de");
        System::loadLanguageFile("form_tag_fields", "de");
        $moduleId = $request->query->get("moduleId");
        $tagFilterIds = $request->query->get('tags') ?: "";
        if ($tagFilterIds !== "") {
            $tagFilterIds = explode(",", $tagFilterIds);
        } else {
            $tagFilterIds = [];
        }

        $requestTypeIds = $request->query->get("types") ?: "";
        if ($requestTypeIds !== "") {
            $requestTypeIds = explode(",", $requestTypeIds);
        } else {
            $requestTypeIds = [];
        }

        $requestLocation = $request->query->get("location");
    
        $moduleModel = ModuleModel::findByPk($moduleId);
        $max = (int) $moduleModel->gutesio_data_max_data;
        if ($max !== 0 && $offset >= $max) {
            return new JsonResponse([]);
        }
        $limit = (int) $moduleModel->gutesio_data_limit ?: 1;
        if ($max !== 0 && ($limit + $offset) > $max) {
            $limit = $max - $offset;
        }
        $params = $request->query->all();
        $mode = intval($moduleModel->gutesio_data_mode);
        if (!count($requestTypeIds) && ($mode === 1 || $mode === 2 || $mode === 4)) {
            $typeIds = $this->getTypeConstraintForModule($moduleModel);
            //$typeIds = array_merge($requestTypeIds, $typeIds);
        } else {
            $typeIds = $requestTypeIds;
        }
        if ($mode === 3) {
            $tagIds = $this->getTagConstraintForModule($moduleModel);
        } else {
            $tagIds = [];
        }

        if ($mode === 5) {
            $elementIds = $this->getElementConstraintForModule($moduleModel);
        } else {
            $elementIds = [];
        }
        if (count($tagFilterIds) > 0) {
            // temporarily ignore offset & limit when tag filter is active
            $limit = 5000;
            $tmpOffset = 0;
        } else {
            $tmpOffset = $offset;
        }
        try {
            if (!$moduleModel->gutesio_data_restrict_postals || empty($moduleModel->gutesio_data_restrict_postals)) {
                $restrictedPostals = [];
            } else {
                $restrictedPostals = explode(",", $moduleModel->gutesio_data_restrict_postals);
                if ($restrictedPostals === false) {
                    $restrictedPostals = [];
                }
            }
            
        } catch(\Throwable $error) {
            $restrictedPostals = [];
        }

        $arrSearchStrings = [];

        if ($requestLocation) {
            $pattern = '/[0-9]{5}/';
            preg_match($pattern, $requestLocation, $matches);
            $foundPostal = false;
            foreach ($matches as $match) {
                $restrictedPostals[] = $match;
                $foundPostal = true;
            }

            if (!$foundPostal) {
                $arrSearchStrings[] = $requestLocation;
            }
        }
        
        // special handling for umlauts
        $searchString = $params && key_exists('filter', $params) ? $params['filter'] : null;
        $params['sorting'] = $moduleModel->gutesio_disable_sorting_filter ? $moduleModel->gutesio_initial_sorting : $params['sorting'];
        if (count($arrSearchStrings) || ($searchString !== null && $searchString !== "")) {
            if ($searchString !== null && $searchString !== "") {
                $arrSearchStrings[] = $searchString;
                if (strpos($searchString, "ß") !== false) {
                    $arrSearchStrings[] = str_replace("ß", "ss", $searchString);
                }
                if (strpos($searchString, "ss") !== false) {
                    $arrSearchStrings[] = str_replace("ss", "ß", $searchString);
                }
                if (strpos($searchString, "ä") !== false) {
                    $arrSearchStrings[] = str_replace("ä", "ae", $searchString);
                }
                if (strpos($searchString, "ae") !== false) {
                    $arrSearchStrings[] = str_replace("ae", "ä", $searchString);
                }
                if (strpos($searchString, "ö") !== false) {
                    $arrSearchStrings[] = str_replace("ö", "oe", $searchString);
                }
                if (strpos($searchString, "oe") !== false) {
                    $arrSearchStrings[] = str_replace("oe", "ö", $searchString);
                }
                if (strpos($searchString, "ü") !== false) {
                    $arrSearchStrings[] = str_replace("ü", "ue", $searchString);
                }
                if (strpos($searchString, "ue") !== false) {
                    $arrSearchStrings[] = str_replace("ue", "ü", $searchString);
                }
            }
            $arrResult = [];
            foreach ($arrSearchStrings as $arrSearchString) {
                $params['filter'] = $arrSearchString;
                $partialResult = $this->showcaseService->loadDataChunk($params, $tmpOffset, $limit, $typeIds, $tagIds, $elementIds, $restrictedPostals);
                if (count($partialResult) > 0 && !$partialResult[0]) {
                    // only one element
                    $partialResult = [$partialResult];
                }
                $arrResult = array_merge($arrResult, $partialResult);
            }
            $data = $arrResult;
        } else {
            $data = $this->showcaseService->loadDataChunk($params, $tmpOffset, $limit, $typeIds, $tagIds, $elementIds, $restrictedPostals);
        }
        
        if ($mode === 4) {
            $tmpData = [];
            foreach ($data as $key => $value) {
                $exit = false;
                $blockedTypeIds = StringUtil::deserialize($moduleModel->gutesio_data_blocked_types);
                foreach ($value['types'] as $type) {
                    if (in_array($type['uuid'], $blockedTypeIds)) {
                        $exit = true;
                        break;
                    }
                }
                if (!$exit) {
                    $tmpData[] = $value;
                }
            }
            $data = $tmpData;
        }
        if (is_array($data) && count($data) > 0 && !$data[0]) {
            // single data entry
            // but array is required by the client
            $data = [$data];
        }
        if (count($tagFilterIds) > 0) {
            $data = $this->applyTagFilter($tagFilterIds, $data);
            // reset limit and limit response data
            $limit = intval($moduleModel->gutesio_data_limit);
            $tmpOffset = $offset;
            if (count($data) > $limit) {
                $data = array_slice($data, $tmpOffset, $limit);
            }
        }

        $clientUuid = $this->checkCookieForClientUuid($request);

        $rowData = [];
        foreach ($data as $key => $row) {
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

            $found = false;
            foreach ($rowData as $existData) {
                if ($existData['uuid'] == $row['uuid']) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $rowData[$key] = $row;
            }
        }

        return new JsonResponse($rowData);
    }

    /**
     * @param $moduleModel
     * @return array|JsonResponse
     */
    public function getAllData($moduleModel)
    {
        $this->framework->initialize(true);
        System::loadLanguageFile("field_translations", "de");
        System::loadLanguageFile("operator_showcase_list", "de");
        System::loadLanguageFile("form_tag_fields", "de");

        $offset = 0;
        $typeIds = [];
        $tagIds = [];
        $elementIds = [];
        $limit = 1000;

        $mode = intval($moduleModel->gutesio_data_mode);
        if ($mode === 1 || $mode === 2 || $mode === 4) {
            $typeIds = $this->getTypeConstraintForModule($moduleModel);
        }
        if ($mode === 3) {
            $tagIds = $this->getTagConstraintForModule($moduleModel);
        }
        if ($mode === 5) {
            $elementIds = $this->getElementConstraintForModule($moduleModel);
        }


        try {
            if (!$moduleModel->gutesio_data_restrict_postals || empty($moduleModel->gutesio_data_restrict_postals)) {
                $restrictedPostals = [];
            } else {
                $restrictedPostals = explode(",", $moduleModel->gutesio_data_restrict_postals);
                if ($restrictedPostals === false) {
                    $restrictedPostals = [];
                }
            }

        } catch(\Throwable $error) {
            $restrictedPostals = [];
        }

        $params['sorting'] = false; //dummy value

        $data = $this->showcaseService->loadDataChunk($params, $offset, $limit, $typeIds, $tagIds, $elementIds, $restrictedPostals);

        if ($mode === 4) {
            $tmpData = [];
            foreach ($data as $key => $value) {
                $exit = false;
                $blockedTypeIds = StringUtil::deserialize($moduleModel->gutesio_data_blocked_types);
                foreach ($value['types'] as $type) {
                    if (in_array($type['uuid'], $blockedTypeIds)) {
                        $exit = true;
                        break;
                    }
                }
                if (!$exit) {
                    $tmpData[] = $value;
                }
            }
            $data = $tmpData;
        }

        return $data;
    }

    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request->cookies->get(self::COOKIE_WISHLIST);
        return $clientUuidCookie === null ? null : $clientUuidCookie;
    }

    private function getTypeConstraintForModule(ModuleModel $moduleModel)
    {
        $db = Database::getInstance();
        $mode = intval($moduleModel->gutesio_data_mode);
        switch ($mode) {
            case 0:
                // no constraint
                $typeIds = [];
                break;
            case 1:
                // type constraint
                $typeIds = StringUtil::deserialize($moduleModel->gutesio_data_type);
                break;
            case 2:
                // directory constraint
                $typeIds = [];
                $directoryUuids = StringUtil::deserialize($moduleModel->gutesio_data_directory);
                $resultTypes = [];
                foreach ($directoryUuids as $directoryUuid) {
                    $arrTypes = $db->prepare("SELECT * FROM tl_gutesio_data_directory_type WHERE `directoryId` = ?")
                        ->execute($directoryUuid)->fetchAllAssoc();
                    $resultTypes = array_merge($resultTypes, $arrTypes);
                }
                foreach ($resultTypes as $type) {
                    if (!in_array($type['typeId'], $typeIds)) {
                        $typeIds[] = $type['typeId'];
                    }
                }
                break;
            case 4:
                $blockedTypeIds = StringUtil::deserialize($moduleModel->gutesio_data_blocked_types);
                $allTypeIds = $db->prepare("SELECT * FROM tl_gutesio_data_type")->execute()->fetchEach('uuid');
                $typeIds = [];
                foreach ($allTypeIds as $typeId) {
                    if (!in_array($typeId, $blockedTypeIds)) {
                        $typeIds[] = $typeId;
                    }
                }
                break;
            default:
                $typeIds = [];
                break;
        }
        return $typeIds;
    }

    private function getTagConstraintForModule(ModuleModel $moduleModel)
    {
        $mode = intval($moduleModel->gutesio_data_mode);
        if ($mode !== 3) {
            return [];
        }
        $tagUuids = StringUtil::deserialize($moduleModel->gutesio_data_tags, true);

        return $tagUuids;
    }

    private function getElementConstraintForModule(ModuleModel $moduleModel)
    {
        $mode = intval($moduleModel->gutesio_data_mode);
        if ($mode !== 5) {
            return [];
        }
        $elementUuids = StringUtil::deserialize($moduleModel->gutesio_data_elements, true);

        return $elementUuids;
    }

    private function applyTagFilter(array $tagIds, array $data)
    {
        $result = [];
        foreach ($data as $key => $datum) {
            $match = true;
            if ($datum['tags']) {
                foreach ($tagIds as $tagId) {
                    $found = false;
                    foreach ($datum['tags'] as $tag) {
                        if ($tag['uuid'] === $tagId) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $match = false;
                    }
                }
                if ($match) {
                    $result[] = $datum;
                }
            }
        }

        return $result;
    }

    protected function getFields(): array
    {
        $fields = [];

        if (C4GUtils::endsWith($this->pageUrl, '.html')) {
            $href = str_replace('.html', '/alias.html', $this->pageUrl);
        } else {
            $href = $this->pageUrl . '/alias';
        }

        if ($this->model->gutesio_data_show_image) {
            $field = new ImageTileField();
            $field->setName("image");
            $field->setWrapperClass("c4g-list-element__image-wrapper");
            $field->setClass("c4g-list-element__image");
            $field->setRenderSection(TileField::RENDERSECTION_HEADER);
            $field->setHref($href);
            $field->setHrefField("alias");
            $field->setExternalLinkField('foreignLink');
            $field->setExternalLinkFieldConditionField("directLink");
            $field->setExternalLinkFieldConditionValue("1");
            $field->setCheckOrientation($this->model->gutesio_data_layoutType !== "plain");
            $fields[] = $field;
        }

        $headline = StringUtil::deserialize($this->model->headline, true);
        $level = (int)str_replace("h", "", $headline['unit']);
        // tile headlines should be smaller than the list headline
        $level = $level + 1;

        $field = new HeadlineTileField();
        $field->setName("name");
        $field->setWrapperClass("c4g-list-element__title-wrapper");
        $field->setClass("c4g-list-element__title");
        $field->setLevel($level);
        $fields[] = $field;

        if ($this->model->gutesio_data_show_city) {
            $field = new TextTileField();
            $field->setName("locationCity");
            $field->setWrapperClass("c4g-list-element__city-wrapper");
            $field->setClass("c4g-list-element__city");
            $fields[] = $field;
        }

        if ($this->model->gutesio_data_show_category) {
            $field = new TextTileField();
            $field->setName("types");
            $field->setWrapperClass("c4g-list-element__types-wrapper");
            $field->setClass("c4g-list-element__types");
            $fields[] = $field;
        }

        if ($this->model->gutesio_data_show_selfHelpFocus) {
            $field = new TextTileField();
            $field->setName("selfHelpFocus");
            $field->setWrapperClass("c4g-list-element__selfHelpFocus-wrapper");
            $field->setClass("c4g-list-element__selfHelpFocus");
            $fields[] = $field;
        }

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
        $field->setRenderSection(TileField::RENDERSECTION_FOOTER);
        $field->setHref($href);
        $field->setLinkText($this->languageRefs['alias_link_text']);
        $field->setExternalLinkField('foreignLink');
        $field->setExternalLinkFieldConditionField("directLink");
        $field->setExternalLinkFieldConditionValue("1");
        $fields[] = $field;

        return $fields;
    }

    private function getInitialData(): array
    {
        return ['randKey' => $this->showcaseService->createRandomKey()];
    }

    private function buildFilter()
    {
        $arrFilter = [];

        if ($this->model->gutesio_enable_filter === '1') {
            if ($this->model->gutesio_enable_ext_filter === '1') {
                $form = new ToggleableForm(new Form());
                $form->setMethod('GET');
                $form->setName("filter_" . $this->model->id);
                $form->setContainerRow(true);
                $form->setToggleableBaseClass('c4g-listfilter');
                $form->setToggleableOnLabel($GLOBALS['TL_LANG']['operator_showcase_list']['filter']['close_filter']);
                $form->setToggleableOffLabel($GLOBALS['TL_LANG']['operator_showcase_list']['filter']['open_filter']);
                $form->setToggleableOnClass('react-c4g-listfilter-opened');
                $form->setHidden($this->model->gutesio_enable_filter !== '1');
            } else {
                $form = new Form();
                $form->setMethod('POST');
                $form->setName("filter_" . $this->model->id);
                $form->setContainerRow(true);
                $form->setClass('c4g-listfilter-default');
                $form->setHidden($this->model->gutesio_enable_filter !== '1');
            }

            $arrFilter['form'] = $form;

            $fields = [];
            $textFilter = new TextFormField();
            $textFilter->setName("filter");
            $textFilter->setLabel($this->languageRefsFrontend['filter']['searchfilter']['label'] ?: '');
            $textFilter->setClassName("form-group");
            $textFilter->setPlaceholder($this->languageRefs['filter_placeholder']);
            $textFilter->setWrappingDiv(true);
            $textFilter->setWrappingDivClass("form-view__searchinput");
            $textFilter->setCache(true); //ToDo module switch
            $textFilter->setEntryPoint($this->model->id);
            $fields[] = $textFilter;

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

            $dataMode = $this->model->gutesio_data_mode;
            $types = $dataMode == '1' ? unserialize($this->model->gutesio_data_type) : [];
            $blockedTypes = $dataMode == '4' ? unserialize($this->model->gutesio_data_blocked_types) : [];

            if ($this->model->gutesio_enable_type_filter) {
                $selectedTypes = unserialize($this->model->gutesio_type_filter_selection);
                $typeField = new SelectFormField();
                $typeField->setName("types");
                $typeField->setLabel($this->languageRefsFrontend['filter']['typefilter']['label'] ?: '');
                $typeField->setClassName("form-view__type-filter");
                $typeField->setPlaceholder("Kategorie auswählen");
                $typeField->setOptions($this->getTypeOptions($selectedTypes ?: $types, $blockedTypes));
                $typeField->setMultiple(true);
                $typeField->setCache(true); //ToDo module switch
                $typeField->setEntryPoint($this->model->id);
                $fields[] = $typeField;
            }

            if ($this->model->gutesio_enable_tag_filter) {
                $tagFilter = new MultiCheckboxWithImageLabelFormField();
                $tagFilter->setName("tags");
                $tagFilter->setLabel('');//$this->languageRefsFrontend['filter']['tagfilter']['label']);
                $tagFilter->setClassName("form-view__tag-filter");
                $tagFilter->setOptions($this->getTagOptions());
                $tagFilter->setOptionClass("tag-filter-item showcase tag-filter__filter-item");
                $tagFilter->setCache(true); //ToDo module switch
                $tagFilter->setEntryPoint($this->model->id);
                $fields[] = $tagFilter;
            }

            if (!$this->model->gutesio_disable_sorting_filter) {
                $sortFilter = new RadioGroupFormField();
                $sortFilter->setName("sorting");
                $sortFilter->setLabel($this->languageRefsFrontend['filter']['sorting']['label'] ?: '');
                $sortFilter->setOptions([
                    'random' => $this->languageRefs['filter']['sorting']['random'],
                    'name_asc' => $this->languageRefs['filter']['sorting']['name_asc'],
                    'name_desc' => $this->languageRefs['filter']['sorting']['name_desc'],
                    'tstamp_desc' => $this->languageRefs['filter']['sorting']['tstamp_desc'],
                    'distance' => $this->languageRefs['filter']['sorting']['distance']
                ]);
                $sortFilter->setClassName("showcase-filter__sorting form-view__sorting");
                $sortFilter->setChecked($this->model->gutesio_initial_sorting);
                $sortFilter->setOptionsClass('c4g-form-check c4g-form-check-inline');
                $sortFilter->setCache(true); //ToDo module switch
                $sortFilter->setEntryPoint($this->model->id);
                $fields[] = $sortFilter;
            }
        } else {
            $form = new Form();
            $form->setMethod('POST');
            $form->setName("filter_" . $this->model->id);
            $form->setContainerRow(true);
            $form->setClass('c4g-listfilter-default');
            $form->setHidden($this->model->gutesio_enable_filter !== '1');
            $arrFilter['form'] = $form;
        }

        // module id field so the id gets transferred when loading data async
        $moduleId = new HiddenFormField();
        $moduleId->setName("moduleId");
        $moduleId->setValue($this->model->id);
        $fields[] = $moduleId;

        $randKeyField = new HiddenFormField();
        $randKeyField->setName("randKey");
        $fields[] = $randKeyField;

        $arrFilter['fields'] = $fields;
        $buttons = [];
        if ($this->model->gutesio_enable_filter === '1') {
            $filterButton = new FilterButton();
            $filterButton->setTargetComponent("tiles");
            $filterButton->setAsyncUrl(self::FILTER_ROUTE);
            $filterButton->setCaption($this->languageRefs['filter']['apply_filter'] ?: '');
            $filterButton->setClassName("c4g-btn c4g-btn-filter");
            $filterButton->setOuterClass("c4g-btn-filter-wrapper");
            $buttons[] = $filterButton;
        } else {
            $filterButton = new FilterButton();
            //$filterButton->setTargetComponent("tiles");
            //$filterButton->setAsyncUrl(self::FILTER_ROUTE);
            $filterButton->setClassName('hidden');
            $buttons[] = $filterButton;
        }
        $arrFilter['buttons'] = $buttons;

        return $arrFilter;
    }
    
    private function getTypeOptions($types = [], $blockedTypes = [])
    {
        $typeResult = [];
        if (is_array($types) && count($types) > 0) {
            $typeStr = implode(',',$types);
            $sql = "SELECT DISTINCT uuid, name FROM tl_gutesio_data_type"
                . " WHERE uuid ".C4GUtils::buildInString($types)." ORDER BY name ASC";
            $typeResult = Database::getInstance()->prepare($sql)->execute(...$types)->fetchAllAssoc();
        } else if (is_array($blockedTypes) && count($blockedTypes) > 0) {
            $typeStr = implode(',',$blockedTypes);
            $sql = "SELECT DISTINCT tl_gutesio_data_type.uuid AS uuid, tl_gutesio_data_type.name AS name FROM tl_gutesio_data_type JOIN tl_gutesio_data_element_type ON tl_gutesio_data_type.uuid = tl_gutesio_data_element_type.typeId"
                . " JOIN tl_gutesio_data_element ON tl_gutesio_data_element_type.elementId = tl_gutesio_data_element.uuid"
                . " WHERE tl_gutesio_data_element.releaseType NOT LIKE 'external' AND tl_gutesio_data_type.uuid NOT IN ('".$typeStr."') ORDER BY name ASC";
            $typeResult = Database::getInstance()->prepare($sql)->execute()->fetchAllAssoc();
        } else {
            $sql = "SELECT DISTINCT tl_gutesio_data_type.uuid AS uuid, tl_gutesio_data_type.name AS name FROM tl_gutesio_data_type JOIN tl_gutesio_data_element_type ON tl_gutesio_data_type.uuid = tl_gutesio_data_element_type.typeId"
                . " JOIN tl_gutesio_data_element ON tl_gutesio_data_element_type.elementId = tl_gutesio_data_element.uuid"
                . " WHERE tl_gutesio_data_element.releaseType NOT LIKE 'external' ORDER BY name ASC";
            $typeResult = Database::getInstance()->prepare($sql)->execute()->fetchAllAssoc();
        }

        $options = [];
        foreach ($typeResult as $result) {
            $options[] = [
                'value' => $result['uuid'],
                'label' => $result['name']
            ];
        }
        
        return $options;
    }

    private function getTagOptions()
    {
        $optionData = [];
        $arrTagIds = StringUtil::deserialize($this->model->gutesio_tag_filter_selection, true);

        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;

        $fileUtils = new FileUtils();

        foreach ($arrTagIds as $arrTagId) {
            $strSelect = "SELECT * FROM tl_gutesio_data_tag WHERE published = 1 AND uuid = ? AND (validFrom IS NULL OR validFrom = 0 OR validFrom <= UNIX_TIMESTAMP() AND (validUntil IS NULL OR validUntil = 0 OR validUntil >= UNIX_TIMESTAMP())) ";
            $tag = Database::getInstance()->prepare($strSelect)->execute($arrTagId)->fetchAssoc();
            if ($tag) {
                if ($tag["technicalKey"] === "tag_opening_hours") {
                    // TODO temporarily remove opening_hours tag since it needs to be handled differently
                    // TODO until then, it would break the filter if it exists as option
                    continue;
                }
                if ($tag["technicalKey"] === "tag_phone_hours") {
                    // TODO temporarily remove opening_hours tag since it needs to be handled differently
                    // TODO until then, it would break the filter if it exists as option
                    continue;
                }
                if ($tag['imageCDN']) {
//                    $objImage = FilesModel::findByUuid(StringUtil::binToUuid($tag['image']));
//                    if ($objImage) {
                        $optionData[$tag['uuid']] = [
                            'src' => $fileUtils->addUrlToPath($cdnUrl,$tag['imageCDN']),
                            'alt' => $tag['name']
                        ];
//                    } else {
//                        //ToDo CDN
//                    }
                } else {
                    $optionData[$tag['uuid']] = [];
                }
            }
        }

        return $optionData;
    }

    protected function getSearchLinks($result = false): array
    {
        $database = Database::getInstance();
        $result = $result ?: $database->prepare('SELECT alias, name FROM tl_gutesio_data_element')->execute()->fetchAllAssoc();
        $links = [];
        if ($result) {
            foreach ($result as $row) {
                $alias = is_array($row) && key_exists('alias', $row) ? $row['alias'] : false;
                $name  = is_array($row) && key_exists('name', $row) ? $row['name'] : false;
                if (!$alias || !$name) {
                    continue;
                }

                if (C4GUtils::endsWith($this->pageUrl, '.html')) {
                    $href = str_replace('.html', '/' . $alias . '.html', $this->pageUrl);
                } else {
                    $href = $this->pageUrl . '/' . $alias;
                }
                $links[] = [
                    'link' => "<a href=\"$href\">".$name."</a>"
                ];
            }
        }
        return $links;
    }

    protected function getMetaData($elements = []) {
        $meta = '';
        $last_key = end(array_keys($elements));
        foreach ($elements as $key=>$row) {
            $alias = $row['alias'];
            $name  = $row['name'];
            $image = $row['image']['src'];

            if (C4GUtils::endsWith($this->pageUrl, '.html')) {
                $href = str_replace('.html', '/' . $alias . '.html', $this->pageUrl);
            } else {
                $href = $this->pageUrl . '/' . $alias;
            }

            $meta .= '{"@type": "ListItem", "name": "'.htmlspecialchars(strip_tags($name)).'", "url": "{{env::url}}/'.$href.'", "image": "'.$image.'"}';

            if ($key != $last_key) {
                $meta .= ',';
            }
        }

        return [$meta,count($elements)];
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

        $stmt = $db->prepare("SELECT alias FROM tl_gutesio_data_element");
        $result = $stmt->execute()->fetchAllAssoc();

        foreach ($result as $res) {
            $objSettings = GutesioOperatorSettingsModel::findSettings();
            $parents = PageModel::findParentsById($objSettings->showcaseDetailPage);
            if ($parents === null || count($parents) < 2 || (int)$parents[count($parents) - 1]->id !== (int)$rootId) {
                continue;
            }
            $url = C4GUtils::replaceInsertTags("{{link_url::" . $objSettings->showcaseDetailPage . "}}");
            if (C4GUtils::endsWith($url, '.html')) {
                $url = str_replace('.html', '/' . $res['alias'] . '.html', $url);
            } else {
                $url = $url . '/' . $res['alias'];
            }
            $pages[] = C4GUtils::replaceInsertTags("{{env::url}}") . '/' . $url;
        }

        return $pages;
    }
}