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
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\Database;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Template;
use gutesio\DataModelBundle\Classes\FileUtils;
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
    
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $this->model = $model;
        ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing_banner.min.css");

        $db = Database::getInstance();
        $mode = $model->gutesio_data_mode;
        $qrForImages = ($model->gutesio_banner_qr_for_images === '1' || $model->gutesio_banner_qr_for_images === 1);
        $arrReturn = [];
        switch ($mode) {
            case 0: {
                $arrElements = $db->prepare('SELECT * FROM tl_gutesio_data_element WHERE displayComply=1')->execute()->fetchAllAssoc();
                break;
            }
            case 1: {
                $types = StringUtil::deserialize($model->gutesio_data_type, true);
                if (!empty($types)) {
                    $in = implode(',', array_fill(0, count($types), '?'));
                    $strSql = "SELECT DISTINCT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_element_type AS con ON con.elementId = elem.uuid
                            WHERE elem.displayComply=1 AND con.typeId IN($in)";
                    $arrElements = $db->prepare($strSql)->execute(...$types)->fetchAllAssoc();
                } else {
                    $arrElements = [];
                }
                break;
            }
            case 2: {
                $directories = StringUtil::deserialize($model->gutesio_data_directory, true);
                if (!empty($directories)) {
                    $in = implode(',', array_fill(0, count($directories), '?'));
                    $strSql = "SELECT DISTINCT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_element_type AS con ON con.elementId = elem.uuid
                                JOIN tl_gutesio_data_directory_type as dirType ON dirType.typeId = con.typeId
                            WHERE elem.displayComply=1 AND dirType.directoryId IN($in)";
                    $arrElements = $db->prepare($strSql)->execute(...$directories)->fetchAllAssoc();
                } else {
                    $arrElements = [];
                }
                break;
            }
            case 3: {
                $arrTags = StringUtil::deserialize($model->gutesio_data_tags, true);
                if (!empty($arrTags)) {
                    $in = implode(',', array_fill(0, count($arrTags), '?'));
                    $strSql = "SELECT DISTINCT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_tag_element AS con ON con.elementId = elem.uuid
                            WHERE elem.displayComply=1 AND con.tagId IN($in)";
                    $arrElements = $db->prepare($strSql)->execute(...$arrTags)->fetchAllAssoc();
                } else {
                    $arrElements = [];
                }
                break;
            }
            case 4: {
                $blockedTypes = StringUtil::deserialize($model->gutesio_data_blocked_types, true);
                if (!empty($blockedTypes)) {
                    $in = implode(',', array_fill(0, count($blockedTypes), '?'));
                    $strSql = "SELECT DISTINCT elem.* FROM tl_gutesio_data_element AS elem 
                                JOIN tl_gutesio_data_element_type AS con ON con.elementId = elem.uuid
                            WHERE elem.displayComply=1 AND con.typeId NOT IN($in)";
                    $arrElements = $db->prepare($strSql)->execute(...$blockedTypes)->fetchAllAssoc();
                } else {
                    $arrElements = [];
                }
                break;
            }
            case 5: {
                $elementUuidArr = StringUtil::deserialize($model->gutesio_data_elements, true);
                if (!empty($elementUuidArr)) {
                    $in = implode(',', array_fill(0, count($elementUuidArr), '?'));
                    $arrElements = $db->prepare("SELECT * FROM tl_gutesio_data_element WHERE displayComply=1 AND uuid IN ($in)")->execute(...$elementUuidArr)->fetchAllAssoc();
                } else {
                    $arrElements = [];
                }
                break;
            }
            case 6: { // Kein Schaufenster laden
                $arrElements = [];
                break;
            }
            default: {
                $arrElements = [];
                break;
            }
        }

        foreach ($arrElements as $element) {
            $arrReturn = $this->getSlidesForElement($element, $arrReturn);
        }
        // additionally mix in images from selected folder(s) (tl_files) if configured
        try {
            $skipUnlinked = ($model->gutesio_banner_skip_unlinked === '1' || $model->gutesio_banner_skip_unlinked === 1);
            $folderUuids = StringUtil::deserialize($model->gutesio_banner_folder, true);
            if (!empty($folderUuids)) {
                $objFolders = FilesModel::findMultipleByUuids($folderUuids);
                if ($objFolders) {
                    // Allow both images and videos from folders
                    $extensions = ['jpg','jpeg','png','gif','webp','bmp','tiff','svg','mp4'];
                    $placeholders = rtrim(str_repeat('?,', count($extensions)), ',');
                    $lang = $GLOBALS['TL_LANGUAGE'] ?? 'de';
                    $seen = [];
                    while ($objFolders->next()) {
                        if ($objFolders->type !== 'folder') { continue; }
                        $folderPath = $objFolders->path;
                        $sql = "SELECT * FROM tl_files WHERE type='file' AND path LIKE ? AND extension IN ($placeholders)";
                        $params = array_merge([$folderPath.'%'], $extensions);
                        $files = $db->prepare($sql)->execute(...$params)->fetchAllAssoc();
                        foreach ($files as $file) {
                            if (isset($seen[$file['path']])) { continue; }
                            $seen[$file['path']] = true;

                            $meta = @unserialize($file['meta']);
                            if (!is_array($meta)) { $meta = []; }
                            $metaLang = [];
                            if (isset($meta[$lang]) && is_array($meta[$lang])) {
                                $metaLang = $meta[$lang];
                            } elseif (!empty($meta) && is_array(reset($meta))) {
                                $metaLang = reset($meta);
                            }
                            $title = $metaLang['title'] ?? basename($file['name'] ?: $file['path']);
                            $alt = $metaLang['alt'] ?? $title;
                            $href = $metaLang['link'] ?? null;
                            if ($skipUnlinked && !$href) { continue; }

                            $srcPath = '/' . ltrim($file['path'], '/');
                            $ext = strtolower((string)($file['extension'] ?? pathinfo($file['path'], PATHINFO_EXTENSION)));
                            if ($ext === 'mp4') {
                                // Build a video slide (no overlay, behaves like image)
                                $arrReturn[] = [
                                    'type'  => 'video',
                                    'video' => [
                                        'src' => $srcPath,
                                    ],
                                    'title' => $title,
                                    'href'  => $href,
                                ];
                            } else {
                                // Image slide
                                $imageSlide = [
                                    'type'  => 'image',
                                    'image' => [
                                        'src' => $srcPath,
                                        'alt' => $alt,
                                    ],
                                    'title' => $title,
                                    'href'  => $href,
                                ];
                                if ($qrForImages && $href) {
                                    $imageSlide['qrcode'] = base64_encode($this->generateQrCode($href));
                                }
                                $arrReturn[] = $imageSlide;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $t) {
            // fail silently, do not block the module rendering
        }
        $arrReturn = $arrReturn ?: [];
        // Determine if the banner will contain only image slides (used for auto-sizing)
        $onlyImages = true;
        foreach ($arrReturn as $s) {
            if (($s['type'] ?? 'image') !== 'image') { $onlyImages = false; break; }
        }
        shuffle($arrReturn);
        // Performance / lazy options
        $lazyMode = (string) ($model->gutesio_banner_lazy_mode ?? '0'); // '0','1','2'
        $deferAssets = ($model->gutesio_banner_defer_assets === '1' || $model->gutesio_banner_defer_assets === 1);
        $limitInitial = (int) ($model->gutesio_banner_limit_initial ?: 1);
        $deferQr = ($model->gutesio_banner_defer_qr === '1' || $model->gutesio_banner_defer_qr === 1);

        if ($lazyMode === '2' && !empty($arrReturn)) {
            // Render only the first N slides initially, defer the rest (for SEO)
            $initial = array_slice($arrReturn, 0, max(1, $limitInitial));
            $deferred = array_slice($arrReturn, max(1, $limitInitial));
            // Keep QR codes within deferred slides as well, so they render after lazy loading.
            // Previously, when `$deferQr` was enabled, QR codes were removed here but never reloaded on the client,
            // resulting in missing QR codes. We keep them to ensure consistent rendering for elements and children.
            $template->slidesInitial = $initial;
            $template->slidesDeferred = !empty($deferred) ? json_encode($deferred) : '';
        } else {
            $template->arr = $arrReturn;
        }

        $template->bannerLazyMode = $lazyMode;
        $template->bannerDeferAssets = $deferAssets;
        $template->bannerLimitInitial = $limitInitial;
        $template->bannerDeferQr = $deferQr;
        // Pass only-images flag so the template/JS can auto-size to image heights when appropriate
        $template->bannerOnlyImages = $onlyImages;

        $template->loadlazy = $model->lazyBanner === "1";
        $template->reloadBanner = $model->reloadBanner === "1";
        // New options for rendering/behavior
        $template->bannerHidePoweredBy = ($model->gutesio_banner_hide_poweredby === '1' || $model->gutesio_banner_hide_poweredby === 1);
        $template->bannerPoweredByText = $model->gutesio_banner_poweredby_text ?: 'Powered by';
        $template->bannerFullscreen = ($model->gutesio_banner_fullscreen === '1' || $model->gutesio_banner_fullscreen === 1);
        $template->bannerMediaBgPortrait = ($model->gutesio_banner_media_bg_portrait === '1' || $model->gutesio_banner_media_bg_portrait === 1);

        // Compute optional custom viewport size (height/width) for the banner
        // Ignore custom values when fullscreen is enabled
        $heightCss = '';
        $widthCss = '';
        if (!$template->bannerFullscreen) {
            try {
                $hv = trim((string)($model->gutesio_banner_height_value ?? ''));
                $hu = trim((string)($model->gutesio_banner_height_unit ?? ''));
                if ($hv !== '' && ctype_digit($hv)) {
                    $allowedHU = ['vh','px','rem','%'];
                    if (in_array($hu, $allowedHU, true)) {
                        $heightCss = $hv . $hu;
                    }
                }
            } catch (\Throwable $t) { /* ignore */ }
            try {
                $wv = trim((string)($model->gutesio_banner_width_value ?? ''));
                $wu = trim((string)($model->gutesio_banner_width_unit ?? ''));
                if ($wv !== '' && ctype_digit($wv)) {
                    $allowedWU = ['%','px','vw','rem'];
                    if (in_array($wu, $allowedWU, true)) {
                        $widthCss = $wv . $wu;
                    }
                }
            } catch (\Throwable $t) { /* ignore */ }
        }
        $template->bannerCustomHeight = $heightCss;
        $template->bannerCustomWidth = $widthCss;
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
        $cdnUrl = $objSettings->cdnUrl;
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
            case 5: { // Kein Inhalt laden
                $arrChilds = [];
                break;
            }
            case 1: {
                $arrTypes = StringUtil::deserialize($model->gutesio_child_type, true);
                if (!empty($arrTypes)) {
                    $in = implode(',', array_fill(0, count($arrTypes), '?'));
                    $strSql = "SELECT DISTINCT child.* FROM tl_gutesio_data_child AS child
                    JOIN tl_gutesio_data_child_connection AS con ON child.uuid = con.childId
                    JOIN tl_gutesio_data_child_type As type ON child.typeId = type.uuid
                WHERE con.elementId = ? AND type.type IN($in)";
                    $params = array_merge([$element['uuid']], $arrTypes);
                    $arrChilds = $db->prepare($strSql)->execute(...$params)->fetchAllAssoc();
                } else {
                    $arrChilds = [];
                }
                break;
            }
            case 2: {
                $arrTypes = StringUtil::deserialize($model->gutesio_child_category, true);
                if (!empty($arrTypes)) {
                    $in = implode(',', array_fill(0, count($arrTypes), '?'));
                    $strSql = "SELECT child.* FROM tl_gutesio_data_child AS child
                    JOIN tl_gutesio_data_child_connection AS con ON child.uuid = con.childId
                WHERE con.elementId = ? AND child.typeId IN($in)";
                    $params = array_merge([$element['uuid']], $arrTypes);
                    $arrChilds = $db->prepare($strSql)->execute(...$params)->fetchAllAssoc();
                } else {
                    $arrChilds = [];
                }
                break;
            }
            case 3: {
                $arrTags = StringUtil::deserialize($model->gutesio_child_tag, true);
                if (!empty($arrTags)) {
                    $in = implode(',', array_fill(0, count($arrTags), '?'));
                    $strSql = "SELECT DISTINCT child.* FROM tl_gutesio_data_child AS child
                    JOIN tl_gutesio_data_child_connection AS con ON child.uuid = con.childId
                    JOIN tl_gutesio_data_child_tag As tag ON child.uuid = tag.childId
                WHERE con.elementId = ? AND tag.tagId IN($in)";
                    $params = array_merge([$element['uuid']], $arrTags);
                    $arrChilds = $db->prepare($strSql)->execute(...$params)->fetchAllAssoc();
                } else {
                    $arrChilds = [];
                }
                break;
            }
            default: {
                $arrChilds = [];
            }
        }
        //$objLogo = FilesModel::findByUuid($element['logo']);
        $fileUtils = new FileUtils();
        $logoSrc = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$element['logoCDN'], '',0, 0, 86400);
        foreach ($arrChilds as $key => $child) {
            if ($this->model->gutesio_max_childs && $this->model->gutesio_max_childs > $key) {
                break;
            }
            $arrReturn = $this->getSlidesForChild($child, $element, $logoSrc, $arrReturn);
        }

        $imageSrc = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$element['imageCDN'], '',2400, 86400);

        $detailRoute =  C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->showcaseDetailPage . '::absolute}}') . '/' . $element['alias'];
        $shortDescription = key_exists('shortDescription', $element) ? $element['shortDescription'] : '';
        $singleEle = [
            'type'  => "element",
            'image' => [
                'src' =>    $imageSrc,
                'alt' =>    $element['name']
            ],
            'title' => $element['name'],
            'slogan' => $element ['displaySlogan'] ?: $shortDescription,
            'href' => $detailRoute,
            //'contact' => $value['name'],
            'qrcode' => base64_encode($this->generateQrCode($detailRoute))
        ];
        if ($logoSrc) {
            $singleEle['logo'] = [
                'src' => $logoSrc,
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
    private function getSlidesForChild (array $child, array $element, $logoSrc, ?array $arrReturn = []) {
        $db = Database::getInstance();
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;
        $fileUtils = new FileUtils();
        $type = $db->prepare('SELECT type,name FROM tl_gutesio_data_child_type
                WHERE uuid = ?')->execute($child['typeId'])->fetchAssoc();
        $termin = '';
        $location = '';
        if ($type['type'] === "event") {
            $event = $db->prepare('SELECT * FROM tl_gutesio_data_child_event WHERE childId=?')->execute($child['uuid'])->fetchAssoc();
            $month = $this->model->loadMonth ?: 6;
            if (($event['beginDate'] + $event['beginTime'] < time()) || ($event['beginDate'] + $event['beginTime'] > (time()+(86400*30*$month)))) { //halbes Jahr im voraus
                return $arrReturn;
            }
            $timezone = new \DateTimeZone('Europe/Berlin');
            $beginDateTime = new \DateTime();
            $beginDateTime->setTimezone($timezone);
            $beginDateTime->setTimestamp($event['beginDate']);
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
            $endTime = (isset($event['endTime']) && !empty($event['endTime']) && ($event['endTime'] !== '0')) ? gmdate('H:i', $event['endTime']) : false;
            if ($endTime && $endTime !== $beginTime) {
                $termin .= " - " . $endTime;
            }
            if ($beginTime) {
                $termin .= " Uhr";
            }
            if ($event['locationElementId'] && $event['locationElementId'] !== $element['uuid']) {
                $locationResult = $db->prepare("SELECT name FROM tl_gutesio_data_element WHERE uuid=?")->execute($event['locationElementId'])->fetchAssoc();
                $location = $locationResult ? $locationResult['name'] : '';
            }
        }

        $offerSrc = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$child['imageCDN'], '',0,0,86400);

        if ($offerSrc && strpos($offerSrc, '/default/')) {
            return $arrReturn; //remove events with default images
        }
        $detailPage = $type['type'] . "DetailPage";
        $detailRoute =  C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->$detailPage . '::absolute}}') . '/' . trim($child['uuid'],'{}');

        $singleEle = [
            'type'  => "event",
            'image' => [
                'src' =>    $offerSrc,
                'alt' =>    $child['name']
            ],
            'dateTime' => $termin,
            'location' => $location,
            'title' => $child['name'],
            'slogan' => $child['shortDescription'],
            'href' => $detailRoute,
            'contact' => $element['name'],
            'qrcode' => base64_encode($this->generateQrCode($detailRoute))
        ];
        if ($logoSrc) {
            $singleEle['logo'] = [
                'src' => $logoSrc,
                'alt' => $element['name']
            ];
        }
        $arrReturn[] = $singleEle;
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