<?php

namespace gutesio\OperatorBundle\Controller;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\ResourceLoader;
use con4gis\MapsBundle\Classes\Services\AreaService;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Database;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Template;
use gutesio\DataModelBundle\Classes\ShowcaseResultConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class NearbyShowcaseListModuleController extends AbstractFrontendModuleController
{

    const TYPE = "nearby_showcase_list_module";

    public function __construct(
        private RouterInterface $router,
        private ContaoFramework $framework,
        private AreaService $areaService
    ) {
    }


    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        ResourceLoader::loadCssResource("/bundles/con4gisframework/dist/css/tiles.min.css");
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing.min.css");
        ResourceLoader::loadJavaScriptResource("/bundles/gutesiooperator/dist/js/nearby_showcase.js|async", ResourceLoader::JAVASCRIPT, "c4g-all");

        $template->setData([
            'data' => [],
            'moduleId' => $model->id,
            'checkPosition' => (bool) $model->gutesio_check_position,
            'detailLink' =>  (bool) $model->gutesio_show_detail_link,
        ]);

        return $template->getResponse();
    }

    /**
     * @Route(
     *      path="/gutesio/operator/nearby_showcase/{moduleId}/{position}",
     *      name="nearby_showcase",
     *      methods={"GET"},
     *      requirements={"position": ".*"}
     *  )
     * @param Request $request
     * @param $offset
     * @return JsonResponse
     */
    #[Route(
        path: '/gutesio/operator/nearby_showcase/{moduleId}/{position}',
        name: 'nearby_showcase',
        methods: ['GET'],
        requirements: ['position' => '.*']
    )]
    public function loadNearestShowcases(Request $request, int $moduleId, string $position)
    {
        $this->framework->initialize();
        $coords = explode(",", $position);
        $moduleModel = ModuleModel::findById($moduleId);

        return new JsonResponse($this->getData($moduleModel, count($coords) === 2, $coords));
    }

    private function getData(ModuleModel $moduleModel, $distanceFiltering = false, $position = [])
    {
        $showcases = [];
        $mode = intval($moduleModel->gutesio_data_mode);
        if (($mode === 1 || $mode === 2 || $mode === 4)) {
            $typeIds = $this->getTypeConstraintForModule($moduleModel);
        }

        if ($mode === 3) {
            $tagIds = $this->getTagConstraintForModule($moduleModel);
        }

        if ($mode === 5) {
            $elementIds = $this->getElementConstraintForModule($moduleModel);
        }

        $limit = $moduleModel->gutesio_data_max_data;

        $db = Database::getInstance();

        if ($moduleModel->gutesio_load_random_showcase) {
            $sql = "SELECT `uuid` FROM tl_gutesio_data_element ORDER BY RAND() LIMIT 1";
            $elementIds = $db->prepare($sql)->execute()->fetchEach('uuid');
        }

        if (is_array($typeIds) && count($typeIds) > 0) {
            $sql = "SELECT elementId FROM tl_gutesio_data_element_type WHERE `typeId` " . C4GUtils::buildInString($typeIds);
            $elementIds = $db->prepare($sql)->execute(...$typeIds)->fetchEach('elementId');
        } else if (is_array($tagIds) && count($tagIds) > 0) {
            $sql = "SELECT elementId FROM tl_gutesio_data_tag_element WHERE `tagId` " . C4GUtils::buildInString($tagIds);
            $elementIds = $db->prepare($sql)->execute(...$typeIds)->fetchEach('elementId');
        }

        if (is_array($elementIds) && count($elementIds) > 0) {
            $sql = "SELECT name, uuid, alias, geox, geoy, imageCDN, opening_hours  FROM tl_gutesio_data_element WHERE `uuid` " . C4GUtils::buildInString($elementIds);
            if (!$distanceFiltering) {
                $sql .= " ORDER BY RAND() LIMIT " . intval($limit);
            }
            $showcases = $db->prepare($sql)->execute(...$elementIds)->fetchAllAssoc();
        }

        if ($distanceFiltering) {
            $showcases = $this->filterShowcasesByDistance($showcases, $limit, $position);
        }

        $converter = new ShowcaseResultConverter();
        $showcases = $converter->convertDbResult($showcases);

        if ($showcases['name']) {
            // put data in array even if only one dataset
            $showcases = [$showcases];
        }

        $redirectPage = $moduleModel->gutesio_data_redirect_page;

        foreach ($showcases as $key => $showcase) {
            $typeName = "";
            $types = [];
            if ($showcase['types'] && is_array($showcase['types'])) {
                foreach ($showcase['types'] as $type) {
                    $types[] = $type['label'];
                }
                $typeName = implode(", ", $types);
            }
            $showcases[$key]['type'] = $typeName;
            $showcases[$key]['redirectUrl'] =
                $this->router->generate("tl_page." . $redirectPage, ['parameters'=>"/".$showcases[$key]['alias']]);
        }

        return $showcases;
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

    private function filterShowcasesByDistance(array $showcases, int $limit, array $position): array
    {
        foreach ($showcases as $key => $showcase) {
            $distance = $this->areaService->calculateDistance($position, [$showcase['geox'], $showcase['geoy']]);
            $showcases[$key]['distance'] = $distance;
        }

        usort($showcases, function($a, $b) {
            return $a['distance'] - $b['distance'];
        });

        return array_slice($showcases, 0, $limit);
    }

    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request->cookies->get('clientUuid');
        return $clientUuidCookie === null ? null : $clientUuidCookie;
    }
}