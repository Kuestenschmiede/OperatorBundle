<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Controller;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Eye\SquareEye;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\Gradient;
use BaconQrCode\Renderer\RendererStyle\GradientType;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\ResourceLoader;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\AddressTileField;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\OSMOpeningHoursTileField;
use con4gis\FrameworkBundle\Classes\TileFields\PhoneTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TagTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TextTileField;
use con4gis\FrameworkBundle\Classes\TileFields\WrapperTileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\Database;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\DataModelBundle\Classes\ShowcaseResultConverter;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildTypeModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use gutesio\OperatorBundle\Classes\Services\ServerService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class BannerModuleController extends AbstractFrontendModuleController
{
    private ?ModuleModel $model = null;
    private OfferLoaderService $offerLoaderService;
    private ServerService $serverService;

    public const TYPE = 'banner_module';

    public function __construct(OfferLoaderService $offerLoaderService, ServerService $serverService)
    {
        $this->offerLoaderService = $offerLoaderService;
        $this->serverService = $serverService;
    }
    
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->model = $model;
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing_banner.min.css");

        $db = Database::getInstance();
        $mode = $model->gutesio_data_mode;
        switch ($mode) {
            case 0: {
                $arrElements = $db->prepare('SELECT * FROM tl_gutesio_data_element WHERE displayComply=1')->execute()->fetchAllAssoc();
                break;
            }
            case 1: {
                $types = unserialize($model->gutesio_data_type);
                $strSql = "SELECT DISTINCT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_element_type AS con ON con.elementId = elem.uuid
                            WHERE elem.displayComply=1 AND con.typeId IN(";
                foreach ($types as $type) {
                    $strSql .= "'" . $type . "',";
                }
                $strSql = trim($strSql, ",") . ")";
                $arrElements = $db->prepare($strSql)->execute()->fetchAllAssoc();
                break;
            }
            case 2: {
                $directories = unserialize($model->gutesio_data_directory);
                $strSql = "SELECT DISTINCT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_element_type AS con ON con.elementId = elem.uuid
                                JOIN tl_gutesio_data_directory_type as dirType ON dirType.typeId = con.typeId
                            WHERE elem.displayComply=1 AND dirType.directoryId IN(";
                foreach ($directories as $directory) {
                    $strSql .= "'" . $directory . "',";
                }
                $strSql = trim($strSql, ",") . ")";
                $arrElements = $db->prepare($strSql)->execute()->fetchAllAssoc();
                break;
            }
            case 3: {
                $arrTags = unserialize($model->gutesio_data_tags);
                $strSql = "SELECT DISTINCT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_tag_element AS con ON con.elementId = elem.uuid
                            WHERE elem.displayComply=1 AND con.tagId IN(";
                foreach ($arrTags as $tag) {
                    $strSql .= "'" . $tag . "',";
                }
                $strSql = trim($strSql, ",") . ")";
                $arrElements = $db->prepare($strSql)->execute()->fetchAllAssoc();
                foreach ($tags as $tag) {
                    $strSql = 'SELECT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_tag_element AS con ON con.elementId = elem.uuid
                            WHERE elem.displayComply=1 AND con.tagId=?';
                    $arrElements = array_merge($arrElements,  $db->prepare($strSql)->execute($tag)->fetchAllAssoc());
                }
                break;
            }
            case 4: {
                $blockedTypes = unserialize($model->gutesio_data_blocked_types);
                $strSql = "SELECT DISTINCT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_element_type AS con ON con.elementId = elem.uuid
                            WHERE elem.displayComply=1 AND con.typeId NOT IN(";
                foreach ($blockedTypes as $blockedType) {
                    $strSql .= "'" . $blockedType . "',";
                }
                $strSql = trim($strSql, ",") . ")";
                $arrElements = $db->prepare($strSql)->execute()->fetchAllAssoc();
                break;
            }
            default: {
                $arrElements = [];
                break;
            }
        }
        foreach ($arrElements as $element) {
            $arrReturn = $this->getSlidesForElement($element ,$arrReturn);
        }
        $arrReturn = $arrReturn ?: [];
        shuffle($arrReturn);
        $template->arr = $arrReturn;
        $template->loadlazy = $model->lazyBanner === "1";
        $template->reloadBanner = $model->reloadBanner === "1";
        $response = $template->getResponse();

        return $response;
    }
    /**
     * get the slides for the element and its children
     * @param array $element
     * @param array|null $arrReturn
     * @return array
     */
    private function getSlidesForElement (array $element, ?array $arrReturn= []) {
        $db = Database::getInstance();
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $model = $this->model;
        $mode = $model->gutesio_child_data_mode;
        switch ($mode) {
            case 0: {
                $strSql = 'SELECT child.* FROM tl_gutesio_data_child AS child
                    JOIN tl_gutesio_data_child_connection AS con ON child.uuid = con.childId
                WHERE con.elementId = ?';
                $arrChilds = $db->prepare($strSql)->execute($element['uuid'])->fetchAllAssoc();
                break;
            }
            case 1: {
                $arrTypes = unserialize($model->gutesio_child_type);
                $strSql = "SELECT DISTINCT child.* FROM tl_gutesio_data_child AS child
                    JOIN tl_gutesio_data_child_connection AS con ON child.uuid = con.childId
                    JOIN tl_gutesio_data_child_type As type ON child.typeId = type.uuid
                WHERE con.elementId = ? AND type.type IN(";
                foreach ($arrTypes as $type) {
                    $strSql .= "'" . $type . "',";
                }
                $strSql = trim($strSql, ",") . ")";
                $arrChilds = $db->prepare($strSql)->execute($element['uuid'])->fetchAllAssoc();
                break;
            }
            case 2: {
                $arrTypes = unserialize($model->gutesio_child_category);
                $strSql = "SELECT child.* FROM tl_gutesio_data_child AS child
                    JOIN tl_gutesio_data_child_connection AS con ON child.uuid = con.childId
                WHERE con.elementId = ? AND child.typeId IN(";
                foreach ($arrTypes as $type) {
                    $strSql .= "'" . $type . "',";
                }
                $strSql = trim($strSql, ",") . ")";
                $arrChilds = $db->prepare($strSql)->execute($element['uuid'])->fetchAllAssoc();
                break;
            }
            case 3: {
                $arrTags = unserialize($model->gutesio_child_tag);
                $strSql = "SELECT DISTINCT child.* FROM tl_gutesio_data_child AS child
                    JOIN tl_gutesio_data_child_connection AS con ON child.uuid = con.childId
                    JOIN tl_gutesio_data_child_tag As tag ON child.uuid = tag.childId
                WHERE con.elementId = ? AND tag.tagId IN(";
                foreach ($arrTags as $tag) {
                    $strSql .= "'" . $tag . "',";
                }
                $strSql = trim($strSql, ",") . ")";
                $arrChilds = $db->prepare($strSql)->execute($element['uuid'])->fetchAllAssoc();
                break;
            }
            default: {
                $arrChilds = [];
            }
        }
        $objLogo = FilesModel::findByUuid($element['logo']);
        foreach ($arrChilds as $key => $child) {
            if ($this->model->gutesio_max_childs && $this->model->gutesio_max_childs > $key) {
                break;
            }
            $arrReturn = $this->getSlidesForChild($child, $element, $objLogo, $arrReturn);
        }
        $objImage = FilesModel::findByUuid($element['imageShowcase']);
        $detailRoute =  Controller::replaceInsertTags('{{link_url::' . $objSettings->showcaseDetailPage . '::absolute}}') . '/' . $element['alias'];

        $singleEle = [
            'type'  => "element",
            'image' => [
                'src' =>    $objImage->path,
                'alt' =>    $element['name'] ?: $objImage->alt
            ],
            'title' => $element['name'],
            'slogan' => $element ['displaySlogan'] ?: $element['shortDescription'],
            //'contact' => $value['name'],
            'qrcode' => base64_encode($this->generateQrCode($detailRoute))
        ];
        if ($objLogo->path) {
            $singleEle['logo'] = [
                'src' => $objLogo->path,
                'alt' => $element['name']
            ];
        }
        $arrReturn[] = $singleEle;
        return $arrReturn;

    }
    /**
     * get the slides for the element and its children
     * @param array $child
     * @param array $element
     * @param FilesModel $objLogo
     * @param array|null $arrReturn
     * @return array
     */
    private function getSlidesForChild (array $child, array $element, FilesModel $objLogo, ?array $arrReturn = []) {
        $db = Database::getInstance();
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $type = $db->prepare('SELECT type,name FROM tl_gutesio_data_child_type
                WHERE uuid = ?')->execute($child['typeId'])->fetchAssoc();
        if ($type['type'] === "event") {
            $event = $db->prepare('SELECT * FROM tl_gutesio_data_child_event WHERE childId=?')->execute($child['uuid'])->fetchAssoc();

            //Events der nächsten 3 Monate
            if (($event['beginDate'] + $event['beginTime'] < time()) || ($event['beginDate'] + $event['beginTime'] > (time()+(86400*90)))) {
                return $arrReturn;
            }
            $timezone = new \DateTimeZone('Europe/London');
            $beginDateTime = new \DateTime();
            $beginDateTime->setTimestamp($event['beginDate']);
            $beginDateTime->setTimezone($timezone);
            $termin = $beginDateTime->format('d.m.Y');


            if ($event['endDate'] && $event['endDate'] !== $event['beginDate']) {
                $endDateTime = new \DateTime();
                $endDateTime->setTimestamp($event['endDate']);
                $endDateTime->setTimezone($timezone);
                $termin .=" - " . $endDateTime->format('d.m.Y');
            }
            $beginTime = $event['beginTime'] && $event['beginTime'] !== 86400 ? gmdate('H:i', $event['beginTime']) : false;
            if ($beginTime) {
                $termin .= ", " . $beginTime;
            }
            $endTime = $event['endTime'] ? gmdate('H:i', $event['endTime']) : false;
            if ($endTime && $endTime !== $beginTime) {
                $termin .= " - " . $endTime;
            }
            if ($beginTime) {
                $termin .= " Uhr";
            }
            if ($event['locationElementId'] && $event['locationElementId'] !== $element['uuid']) {
                $location = $db->prepare("SELECT name FROM tl_gutesio_data_element WHERE uuid=?")->execute($event['locationElementId'])->fetchAssoc();
                $location = $location['name'];
            }
        }
        $objImage = $child['imageOffer'] && FilesModel::findByUuid($child['imageOffer']) ? FilesModel::findByUuid($child['imageOffer']) : FilesModel::findByUuid($child['image']);

        if ($objImage && $objImage->path && strpos($objImage->path, '/default/')) {
            return $arrReturn; //remove events with default images
        }
        $detailPage = $type['type'] . "DetailPage";
        $detailRoute =  Controller::replaceInsertTags('{{link_url::' . $objSettings->$detailPage . '::absolute}}') . '/' . trim($child['uuid'],'{}');

        $singleEle = [
            'type'  => "event",
            'image' => [
                'src' =>    $objImage->path,
                'alt' =>    $child['name'] ?: $objImage->alt
            ],
            'dateTime' => $termin,
            'location' => $location,
            'title' => $child['name'],
            'slogan' => $child['shortDescription'],
            'contact' => $element['name'],
            'qrcode' => base64_encode($this->generateQrCode($detailRoute))
        ];
        if ($objLogo->path) {
            $singleEle['logo'] = [
                'src' => $objLogo->path,
                'alt' => $element['name']
            ];
        }
        $arrReturn[] = $singleEle;
        $childs++;
        return $arrReturn;
    }
    private function generateQrCode (String $link) {
        $eye = SquareEye::instance();
        $squareModule = SquareModule::instance();

        $eyeFill = new EyeFill(new Rgb(0, 155, 233), new Rgb(0, 155, 233));
        $gradient = new Gradient(new Rgb(13, 59, 93), new Rgb(13, 59, 93), GradientType::HORIZONTAL());

        $renderer = new ImageRenderer(
            new RendererStyle(
                400,
                2,
                $squareModule,
                $eye,
                Fill::withForegroundGradient(new Rgb(255, 255, 255), $gradient, $eyeFill, $eyeFill, $eyeFill)
            ),
            new ImagickImageBackEnd('png')
        );

        $writer = new Writer($renderer);
        $return = $writer->writeString($link);
        return $return;
    }
}