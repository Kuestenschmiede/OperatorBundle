<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Controller;

use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
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
        // TODO verschiedene Ladetypen berücksichtigen
        $mode = $model->gutesio_data_mode;
        switch ($mode) {
            case 0: {
                $arrElements = $db->prepare('SELECT * FROM tl_gutesio_data_element WHERE displayComply=1')->execute()->fetchAllAssoc();
                break;
            }
            case 1: {
                $types = unserialize($model->gutesio_data_type);
                $arrElements = [];
                foreach ($types as $type) {
                    $strSql = 'SELECT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_element_type AS con 
                            WHERE elem.displayComply=1 AND con.typeId=?';
                    $arrElements = array_merge($arrElements,  $db->prepare($strSql)->execute($type)->fetchAllAssoc());
                }

                break;
            }
            case 2: {
                $directories = unserialize($model->gutesio_data_directory);
                break;
            }
            case 3: {
                $tags = unserialize($model->gutesio_data_tags);
                break;
            }
            case 4: {
                $blockedTypes = unserialize($model->gutesio_data_blocked_types);
                break;
            }
        }
        //$arrElements = $db->prepare('SELECT * FROM tl_gutesio_data_element ')->execute()->fetchAllAssoc();
        foreach ($arrElements as $element) {
            $arrReturn = $this->getSlidesForElement($element, $template ,$arrReturn);
        }
        $arrReturn = $arrReturn ?: [];
        shuffle($arrReturn);
        $template->arr = $arrReturn;
        $response = $template->getResponse();

        return $response;
    }
    private function getSlidesForElement ($element, $template, $arrReturn = []) {
        $db = Database::getInstance();
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $result = $db->prepare('SELECT child.* FROM tl_gutesio_data_child AS child
                    JOIN tl_gutesio_data_child_connection AS con ON child.uuid = con.childId
                Where con.elementId = ?')->execute($element['uuid'])->fetchAllAssoc();
        $objLogo = FilesModel::findByUuid($element['logo']);
        $childs = 0;
        foreach ($result as $value) {
            $type = $db->prepare('SELECT type,name FROM tl_gutesio_data_child_type
                Where uuid = ?')->execute($value['typeId'])->fetchAssoc();
            if ($type['type'] === "event") {
                $event = $db->prepare('SELECT * FROM tl_gutesio_data_child_event WHERE childId=?')->execute($value['uuid'])->fetchAssoc();

                //Events der nächsten 3 Monate
                if (($event['beginDate'] + $event['beginTime'] < time()) || ($event['beginDate'] + $event['beginTime'] > (time()+(86400*90)))) {
                    continue;
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
            $objImage = $value['imageOffer'] && FilesModel::findByUuid($value['imageOffer']) ? FilesModel::findByUuid($value['imageOffer']) : FilesModel::findByUuid($value['image']);

            if ($objImage && $objImage->path && strpos($objImage->path, '/default/')) {
                continue; //remove events with default images
            }
            $detailPage = $type['type'] . "DetailPage";
            $detailRoute =  Controller::replaceInsertTags('{{link_url::' . $objSettings->$detailPage . '::absolute}}') . '/' . trim($value['uuid'],'{}');

            $singleEle = [
                'type'  => "event",
                'image' => [
                    'src' =>    $objImage->path,
                    'alt' =>    $value['name'] ?: $objImage->alt
                ],
                'dateTime' => $termin,
                'location' => $location,
                'title' => $value['name'],
                'slogan' => $value['shortDescription'],
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
            if ($this->model->gutesio_max_childs && ($childs >= $this->model->gutesio_max_childs)) {
                break;
            }
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
    private function generateQrCode ($link) {
        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new ImagickImageBackEnd('png')
        );
        $writer = new Writer($renderer);
        $return = $writer->writeString($link);
        return $return;
    }
}