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
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\DistanceField;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TagTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TextTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use con4gis\FrameworkBundle\Traits\AutoItemTrait;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\Database;
use Contao\FrontendTemplate;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Template;
use gutesio\OperatorBundle\Classes\Services\ShowcaseService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ShowcaseCarouselModuleController extends AbstractFrontendModuleController
{
    use AutoItemTrait;

    const TYPE = 'showcase_tile_list_module';

    private $showcaseService = null;

    private $model = null;

    /**
     * ShowcaseCarouselModuleController constructor.
     */
    public function __construct()
    {
        $this->showcaseService = ShowcaseService::getInstance();
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->model = $model;
        if ($model->gutesio_carousel_template) {
            $template = new FrontendTemplate($model->gutesio_carousel_template);
        }
        $data = $this->getData();
        $template->data = $data;
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/vendor/owl/owl.carousel.min.css");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/vendor/owl/owl.theme.default.min.css");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing_carousel.min.css");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/vendor/owl/owl.carousel.min.js");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/c4g_all.js|async", ResourceLoader::JAVASCRIPT, "c4g-all");
    
        return $template->getResponse();
    }

    private function getData()
    {
        $redirectPage = Controller::replaceInsertTags("{{link_url::{$this->model->gutesio_data_redirect_page}}}");
        if (C4GUtils::endsWith($redirectPage, '.html')) {
            $href = str_replace('.html', '/alias.html', $redirectPage);
        } else {
            $href = $redirectPage . '/alias';
        }

        $maxData = intval($this->model->gutesio_data_max_data);
        $tmpLimit = 500;
        $typeIds = $this->getTypeConstraintForModule();
        $tagIds = $this->getTagConstraintForModule();
        $postals = $this->model->gutesio_data_restrict_postals;
        if ($postals === "") {
            $arrPostals = [];
        } else {
            $arrPostals = explode(",", $postals);
        }
        $data = $this->showcaseService->loadDataChunk(
            ['sorting' => 'random', 'randKey' => $this->showcaseService->createRandomKey()],
            0,
            $tmpLimit,
            $typeIds,
            $tagIds,
            $arrPostals
        );
        $isSingleEntry = (count($data) > 1) && (!$data[0]);
        if ($isSingleEntry) {
            $data = [$data];
        }
        if (count($data) > $maxData) {
            $data = array_slice($data, 0, $maxData);
        }
        foreach ($data as $key => $datum) {
            $data[$key]['href'] = str_replace("alias", $datum['alias'], $href);
        }


        return $data;
    }

    private function getTypeConstraintForModule()
    {
        $moduleModel = $this->model;
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

    private function getTagConstraintForModule()
    {
        $model = $this->model;
        $mode = intval($model->gutesio_data_mode);
        if ($mode === 3) {
            return StringUtil::deserialize($model->gutesio_data_tags, true);
        } else {
            return [];
        }
    }

}