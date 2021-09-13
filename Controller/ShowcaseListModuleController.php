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
use Contao\Controller;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Database;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\ShowcaseService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShowcaseListModuleController extends \Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController
{
    use AutoItemTrait;

    protected $tileList = null;
    protected $tileItems = [];
    protected $model = null;

    const AJAX_GET_ROUTE = '/gutesio/operator/showcase_tile_list_data/{offset}';
    const FILTER_ROUTE = '/gutesio/operator/showcase_tile_list/filter';
    const TYPE = 'showcase_list_module';
    const COOKIE_WISHLIST = "clientUuid";

    private $showcaseService = null;

    private $languageRefs = [];
    private $languageRefsFrontend = [];


    public function __construct(ShowcaseService $showcaseService)
    {
        $this->showcaseService = $showcaseService;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        global $objPage;
        $this->model = $model;
        $this->setAlias();
        $redirectPage = $model->gutesio_data_redirect_page;
        $redirectUrl = Controller::replaceInsertTags("{{link_url::$redirectPage}}");
        if ($redirectPage && $redirectUrl) {
            if (!C4GUtils::endsWith($this->pageUrl, $redirectUrl)) {
                $this->pageUrl = $redirectUrl;
            }
        }
        if ($this->alias !== "") {
            throw new RedirectResponseException($this->pageUrl);
        }
        ResourceLoader::loadJavaScriptResource("/bundles/con4gisframework/build/c4g-framework.js", ResourceLoader::JAVASCRIPT, "c4g-framework");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js", ResourceLoader::JAVASCRIPT, "c4g-all");
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
                'tags' => []
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
            $sc = new SearchConfiguration();
            $sc->addData($this->getSearchLinks(), ['link']);
            $template->searchHTML = $sc->getHTML();
        }

        return $template->getResponse();
    }

    protected function getTileList(): TileList
    {
        $tileList = new TileList();
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
     * @Route("/gutesio/operator/showcase_tile_list_data/{offset}", name="showcase_tile_list_data", methods={"GET"}, requirements={"offset"="\d+"})
     * @param Request $request
     * @param $offset
     * @return JsonResponse
     */
    public function getDataAction(Request $request, int $offset)
    {
        $this->get('contao.framework')->initialize(true);
        System::loadLanguageFile("field_translations", "de");
        System::loadLanguageFile("operator_showcase_list", "de");
        System::loadLanguageFile("form_tag_fields", "de");
        $moduleId = $request->query->get("moduleId");
        $tagFilterIds = $request->query->get('tags');
        if ($tagFilterIds === "" || $tagFilterIds === null) {
            $tagFilterIds = [];
        } else {
            $tagFilterIds = explode(",", $tagFilterIds);
        }
        $requestTypeIds = $request->query->get("types");
        if ($requestTypeIds === "" || $requestTypeIds === null) {
            $requestTypeIds = [];
        } else {
            $requestTypeIds = explode(",", $requestTypeIds);
        }
    
        $moduleModel = ModuleModel::findByPk($moduleId);
        $max = (int) $moduleModel->gutesio_data_max_data;
        if ($max !== 0 && $offset >= $max) {
            return new JsonResponse([]);
        }
        $limit = (int) $moduleModel->gutesio_data_limit ?: 1;
        if ($max !== 0 && $limit + $offset > $max) {
            $limit = $max - $offset;
        }
        $params = $request->query->all();
        $mode = intval($moduleModel->gutesio_data_mode);
        if ($mode === 1 || $mode === 2) {
            $typeIds = $this->getTypeConstraintForModule($moduleModel);
        } else {
            $typeIds = $requestTypeIds;
        }
        if ($mode === 3) {
            $tagIds = $this->getTagConstraintForModule($moduleModel);
        } else {
            $tagIds = [];
        }
        if (count($tagFilterIds) > 0) {
            // temporarily ignore offset & limit when tag filter is active
            $limit = 5000;
            $tmpOffset = 0;
        } else {
            $tmpOffset = $offset;
        }
        try {
            if ($moduleModel->gutesio_data_restrict_postals === "") {
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
        
        $data = $this->showcaseService->loadDataChunk($params, $tmpOffset, $limit, $typeIds, $tagIds, $restrictedPostals);
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
            $data[$key] = $row;
        }

        return new JsonResponse($data);
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

        $field = new ImageTileField();
        $field->setName("imageList");
        $field->setWrapperClass("c4g-list-element__image-wrapper");
        $field->setClass("c4g-list-element__image");
        $field->setRenderSection(TileField::RENDERSECTION_HEADER);
        $fields[] = $field;

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

    private function getInitialData(): array
    {
        return ['randKey' => $this->showcaseService->createRandomKey()];
    }

    private function buildFilter()
    {
        $arrFilter = [];
        $form = new ToggleableForm(new Form());
        $form->setName("filter_" . $this->model->id);
        $form->setMethod("GET");
        $form->setContainerRow(true);
        $form->setToggleableBaseClass('c4g-listfilter');
        $form->setToggleableOnLabel($GLOBALS['TL_LANG']['operator_showcase_list']['filter']['close_filter']);
        $form->setToggleableOffLabel($GLOBALS['TL_LANG']['operator_showcase_list']['filter']['open_filter']);
        $form->setToggleableOnClass('react-c4g-listfilter-opened');
        $form->setHidden($this->model->gutesio_enable_filter !== '1');
        $arrFilter['form'] = $form;

        $fields = [];
        $textFilter = new TextFormField();
        $textFilter->setName("filter");
        $textFilter->setLabel($this->languageRefsFrontend['filter']['searchfilter']['label']);
        $textFilter->setClassName("form-group");
        $textFilter->setPlaceholder($this->languageRefs['filter_placeholder']);
        $textFilter->setWrappingDiv(true);
        $textFilter->setWrappingDivClass("form-view__searchinput");
        $fields[] = $textFilter;

        if ($this->model->gutesio_enable_type_filter) {
            $typeField = new SelectFormField();
            $typeField->setName("types");
            $typeField->setLabel($this->languageRefsFrontend['filter']['typefilter']['label']);
            $typeField->setClassName("form-view__type-filter");
            $typeField->setPlaceholder("Kategorie auswählen");
            $typeField->setOptions($this->getTypeOptions());
            $typeField->setMultiple(true);
            $fields[] = $typeField;
        }

        if ($this->model->gutesio_enable_tag_filter) {
            $tagFilter = new MultiCheckboxWithImageLabelFormField();
            $tagFilter->setName("tags");
            $tagFilter->setLabel($this->languageRefsFrontend['filter']['tagfilter']['label']);
            $tagFilter->setClassName("form-view__tag-filter");
            $tagFilter->setOptions($this->getTagOptions());
            $tagFilter->setOptionClass("tag-filter-item showcase tag-filter__filter-item");
            $fields[] = $tagFilter;
        }

        $sortFilter = new RadioGroupFormField();
        $sortFilter->setName("sorting");
        $sortFilter->setLabel($this->languageRefsFrontend['filter']['sorting']['label']);
        $sortFilter->setOptions([
            'random' => $this->languageRefs['filter']['sorting']['random'],
            'name_asc' => $this->languageRefs['filter']['sorting']['name_asc'],
            'name_desc' => $this->languageRefs['filter']['sorting']['name_desc'],
            'tstamp_desc' => $this->languageRefs['filter']['sorting']['tstamp_desc'],
            'distance' => $this->languageRefs['filter']['sorting']['distance']
        ]);
        $sortFilter->setClassName("showcase-filter__sorting form-view__sorting");
        $sortFilter->setChecked("random");
        $sortFilter->setOptionsClass('c4g-form-check c4g-form-check-inline');
        $fields[] = $sortFilter;
        


        // module id field so the id gets transferred when loading data async
        $moduleId = new HiddenFormField();
        $moduleId->setName("moduleId");
        $moduleId->setValue($this->model->id);
        $fields[] = $moduleId;

        $arrFilter['fields'] = $fields;

        $buttons = [];
        $filterButton = new FilterButton();
        $filterButton->setTargetComponent("tiles");
        $filterButton->setAsyncUrl(self::FILTER_ROUTE);
        $filterButton->setCaption($this->languageRefs['filter']['apply_filter']);
        $filterButton->setClassName("c4g-btn c4g-btn-filter");
        $filterButton->setOuterClass("c4g-btn-filter-wrapper");
        $buttons[] = $filterButton;
        $arrFilter['buttons'] = $buttons;

        return $arrFilter;
    }
    
    private function getTypeOptions()
    {
        $sql = "SELECT * FROM tl_gutesio_data_type";
        $typeResult = Database::getInstance()->prepare($sql)->execute()->fetchAllAssoc();
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

        foreach ($arrTagIds as $arrTagId) {
            $strSelect = "SELECT * FROM tl_gutesio_data_tag WHERE published = 1 AND uuid = ? AND (validFrom = 0 OR validFrom >= UNIX_TIMESTAMP() AND (validUntil = 0 OR validUntil <= UNIX_TIMESTAMP())) ";
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

    protected function getSearchLinks(): array
    {
        $database = Database::getInstance();
        $result = $database->prepare('SELECT alias FROM tl_gutesio_data_element')->execute()->fetchAllAssoc();
        $links = [];
        foreach ($result as $row) {
            $alias = $row['alias'];
            if (C4GUtils::endsWith($this->pageUrl, '.html')) {
                $href = str_replace('.html', '/' . $alias . '.html', $this->pageUrl);
            } else {
                $href = $this->pageUrl . '/' . $alias;
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

        $stmt = $db->prepare("SELECT alias FROM tl_gutesio_data_element");
        $result = $stmt->execute()->fetchAllAssoc();

        foreach ($result as $res) {
            $objSettings = GutesioOperatorSettingsModel::findSettings();
            $parents = PageModel::findParentsById($objSettings->showcaseDetailPage);
            if (sizeof($parents) < 2 || (int)$parents[sizeof($parents) - 1]->id !== (int)$rootId) {
                continue;
            }
            $url = Controller::replaceInsertTags("{{link_url::" . $objSettings->showcaseDetailPage . "}}");
            if (C4GUtils::endsWith($url, '.html')) {
                $url = str_replace('.html', '/' . $res['alias'] . '.html', $url);
            } else {
                $url = $url . '/' . $res['alias'];
            }
            $pages[] = Controller::replaceInsertTags("{{env::url}}") . '/' . $url;
        }

        return $pages;
    }
}