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
        // Optional GET parameter guard to avoid unintended costly requests
        try {
            $guardParam = trim((string)($model->gutesio_banner_guard_param ?? ''));
            if ($guardParam !== '') {
                $expected = (string)($model->gutesio_banner_guard_value ?? '');
                $has = $request->query->has($guardParam);
                $val = $has ? (string)$request->query->get($guardParam) : null;
                $ok = $has && ($expected === '' || $val === $expected);
                if (!$ok) {
                    // Return an empty response if guard is not satisfied (prevents DB load and rendering)
                    return new Response('', 204);
                }
            }
        } catch (\Throwable $t) {
            // On any error with guard evaluation, fail closed to be safe
            return new Response('', 204);
        }

        ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing_banner.min.css");

        $db = Database::getInstance();
        $mode = $model->gutesio_data_mode;
        $qrForImages = ($model->gutesio_banner_qr_for_images === '1' || $model->gutesio_banner_qr_for_images === 1);
        $playVideos  = ($model->gutesio_banner_play_videos === '1' || $model->gutesio_banner_play_videos === 1);
        $muteVideos  = ($model->gutesio_banner_mute_videos === '1' || $model->gutesio_banner_mute_videos === 1);
        $strictImages = ($model->gutesio_banner_strict_images === '1' || $model->gutesio_banner_strict_images === 1);
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
        // additionally mix in media from selected folder(s) (tl_files) if configured
        try {
            $skipUnlinked = ($model->gutesio_banner_skip_unlinked === '1' || $model->gutesio_banner_skip_unlinked === 1);
            $folderUuids = StringUtil::deserialize($model->gutesio_banner_folder, true);
            if (!empty($folderUuids)) {
                $objFolders = FilesModel::findMultipleByUuids($folderUuids);
                if ($objFolders) {
                    // Allow images always; allow videos only if flag is set
                    $extensions = ['jpg','jpeg','png','gif','webp','bmp','tiff','svg'];
                    if ($playVideos) { $extensions[] = 'mp4'; }
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
                                if (!$playVideos) { continue; }
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

        // Theme color (accent) handling: read from module, sanitize, compute RGB/contrast and pass to template
        $defaultHex = '#2ea1db';
        $hexRaw = trim((string)($model->gutesio_banner_theme_color ?? $defaultHex));
        // Accept values with or without leading '#', and both 3/6-digit
        if ($hexRaw !== '' && $hexRaw[0] !== '#') {
            $hexRaw = '#' . $hexRaw;
        }
        $isValidHex = (bool) preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hexRaw);
        $hex = $isValidHex ? $hexRaw : $defaultHex;
        // Normalize to 6-digit
        if (strlen($hex) === 4) {
            $hex = sprintf('#%1$s%1$s%2$s%2$s%3$s%3$s', $hex[1], $hex[2], $hex[3]);
        }
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        $template->bannerThemeColorHex = $hex;
        $template->bannerThemeColorRgb = $r . ', ' . $g . ', ' . $b;

        // Compute contrast color (black or white) for text on accent backgrounds
        // Relative luminance (sRGB) heuristic
        $sr = $r / 255; $sg = $g / 255; $sb = $b / 255;
        $lin = function($c){ return ($c <= 0.03928) ? ($c/12.92) : pow(($c+0.055)/1.055, 2.4); };
        $L = 0.2126 * $lin($sr) + 0.7152 * $lin($sg) + 0.0722 * $lin($sb);
        $contrastHex = ($L > 0.5) ? '#000000' : '#ffffff';
        $template->bannerThemeContrastHex = $contrastHex;

        // Optional: Overlay-Transparenz (0–100 %) -> in 0..1 normiert als CSS-Variable im Template
        try {
            $op = (int) ($model->gutesio_banner_overlay_opacity ?? 0);
            if ($op < 0) { $op = 0; }
            if ($op > 100) { $op = 100; }
            if ($op > 0) {
                // auf zwei Nachkommastellen runden, als String ausgeben (z. B. "0.7")
                $template->bannerOverlayOpacity = rtrim(rtrim(number_format($op / 100, 2, '.', ''), '0'), '.');
            }
        } catch (\Throwable $t) {
            // Ignorieren – Template nutzt Default-Fallbacks
        }

        $template->loadlazy = $model->lazyBanner === "1";
        $template->reloadBanner = $model->reloadBanner === "1";
        // New options for rendering/behavior
        $template->bannerHidePoweredBy = ($model->gutesio_banner_hide_poweredby === '1' || $model->gutesio_banner_hide_poweredby === 1);
        $template->bannerPoweredByText = $model->gutesio_banner_poweredby_text ?: 'Powered by';
        $template->bannerFullscreen = ($model->gutesio_banner_fullscreen === '1' || $model->gutesio_banner_fullscreen === 1);
        $template->bannerMediaBgPortrait = ($model->gutesio_banner_media_bg_portrait === '1' || $model->gutesio_banner_media_bg_portrait === 1);
        // Neuer globaler Schalter: Bilder vollflächig im Hintergrund (Default aktiv)
        $template->bannerMediaBgFull = ($model->gutesio_banner_media_bg_full === '1' || $model->gutesio_banner_media_bg_full === 1);
        // Footer alignment option (left align contact + logo)
        $template->bannerFooterAlignLeft = ($model->gutesio_banner_footer_align_left === '1' || $model->gutesio_banner_footer_align_left === 1);
        // Optional ad label ("Anzeige") toggle
        $template->bannerShowAdLabel = ($model->gutesio_banner_show_ad_label === '1' || $model->gutesio_banner_show_ad_label === 1);
        // Open links in new tab option
        $template->bannerLinksNewTab = ($model->gutesio_banner_links_new_tab === '1' || $model->gutesio_banner_links_new_tab === 1);
        // Optional overlay on event video slides (location + date/time)
        $template->bannerShowEventOverlay = ($model->gutesio_banner_show_event_overlay === '1' || $model->gutesio_banner_show_event_overlay === 1);
        // Hide footer on video slides
        $template->bannerHideFooterOnVideos = ($model->gutesio_banner_hide_footer_on_videos === '1' || $model->gutesio_banner_hide_footer_on_videos === 1);

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
        // expose mute flag for template rendering of HTML5 <video> and YouTube iframes
        $template->bannerMuteVideos = $muteVideos;
        // expose kiosk/chromium mode (force sound) and show-sound-button flag
        try {
            $kioskMode = ($model->gutesio_kiosk_mode === '1' || $model->gutesio_kiosk_mode === 1);
        } catch (\Throwable $t) { $kioskMode = false; }
        try {
            $showSoundBtn = ($model->gutesio_banner_show_sound_button === '1' || $model->gutesio_banner_show_sound_button === 1);
        } catch (\Throwable $t) { $showSoundBtn = true; }
        $template->bannerKioskMode = $kioskMode;
        $template->bannerShowSoundButton = $showSoundBtn;
        // Video timeout (seconds): if > 0, a video may run at most this duration; 0 means full length
        try {
            $vt = (int) ($model->gutesio_banner_video_timeout ?? 180);
            if ($vt < 0) { $vt = 0; }
            // Clamp to sane bounds (min 5s when non-zero, max 3600s)
            if ($vt !== 0) { $vt = max(5, min(3600, $vt)); }
            $template->bannerVideoTimeout = $vt;
        } catch (\Throwable $t) {
            $template->bannerVideoTimeout = 180;
        }
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
        // Restore legacy ImageCache handling: serves from local cache if present, otherwise fetches once from CDN
        $logoSrc = '';
        if (!empty($element['logoCDN'])) {
            if (($this->model->gutesio_banner_strict_images === '1' || $this->model->gutesio_banner_strict_images === 1)) {
                $logoSrc = $fileUtils->addUrlToPathAndGetImageStrict($cdnUrl, $element['logoCDN']);
            } else {
                $logoSrc = $fileUtils->addUrlToPathAndGetImage($cdnUrl, $element['logoCDN']);
            }
        }
        foreach ($arrChilds as $key => $child) {
            // Respect the optional maximum number of children to include.
            // Break once we have added the configured amount. Off-by-one fixed: allow indexes 0..(max-1).
            if ($this->model->gutesio_max_childs && $key >= (int) $this->model->gutesio_max_childs) {
                break;
            }
            $arrReturn = $this->getSlidesForChild($child, $element, $logoSrc, $arrReturn);
        }
        // Restore legacy ImageCache handling for main element image (strict variant optionally)
        $imageSrc = '';
        if (!empty($element['imageCDN'])) {
            if (($this->model->gutesio_banner_strict_images === '1' || $this->model->gutesio_banner_strict_images === 1)) {
                $imageSrc = $fileUtils->addUrlToPathAndGetImageStrict($cdnUrl, $element['imageCDN']);
            } else {
                $imageSrc = $fileUtils->addUrlToPathAndGetImage($cdnUrl, $element['imageCDN']);
            }
        }

        $detailRoute =  C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->showcaseDetailPage . '::absolute}}') . '/' . $element['alias'];
        // Normalize and sanitize textual fields. Some instances store already HTML-encoded content
        // (e.g., quotes as &quot; / &#34;). Decode entities first, then strip tags; escaping is handled in the template.
        $rawElementName = html_entity_decode((string)($element['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeElementName = strip_tags($rawElementName);
        $shortDescription = key_exists('shortDescription', $element) ? $element['shortDescription'] : '';
        $rawShort = html_entity_decode((string)$shortDescription, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeShortDescription = strip_tags($rawShort);
        // In strict mode, skip element slide if we don't have a valid image URL
        if (($this->model->gutesio_banner_strict_images === '1' || $this->model->gutesio_banner_strict_images === 1) && empty($imageSrc)) {
            return $arrReturn;
        }

        $singleEle = [
            'type'  => "element",
            'image' => [
                'src' =>    $imageSrc,
                'alt' =>    $safeElementName
            ],
            'title' => $safeElementName,
            'slogan' => (function($val){
                $decoded = html_entity_decode((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return strip_tags($decoded);
            })(($element['displaySlogan'] ?? '')) ?: $safeShortDescription,
            'href' => $detailRoute,
            //'contact' => $value['name'],
            'qrcode' => base64_encode($this->generateQrCode($detailRoute))
        ];
        if ($logoSrc) {
            $singleEle['logo'] = [
                'src' => $logoSrc,
                'alt' => $safeElementName
            ];
        }
        $arrReturn[] = $singleEle;
        return $arrReturn;

    }

    /**
     * Extract YouTube video ID from common URL formats.
     */
    private function extractYouTubeId(string $url): ?string
    {
        $u = trim($url);
        if ($u === '') { return null; }
        // youtu.be/<id>
        if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $u, $m)) { return $m[1]; }
        // youtube.com/watch?v=<id>
        if (preg_match('~v=([A-Za-z0-9_-]{6,})~', $u, $m)) { return $m[1]; }
        // youtube.com/embed/<id>
        if (preg_match('~/embed/([A-Za-z0-9_-]{6,})~', $u, $m)) { return $m[1]; }
        // shorts/<id>
        if (preg_match('~/shorts/([A-Za-z0-9_-]{6,})~', $u, $m)) { return $m[1]; }
        return null;
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
        $hideEventEndTime = ($this->model->gutesio_banner_hide_event_endtime === '1' || $this->model->gutesio_banner_hide_event_endtime === 1);
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
            if (!$hideEventEndTime && $endTime && $endTime !== $beginTime) {
                $termin .= " - " . $endTime;
            }
            if ($beginTime) {
                $termin .= " Uhr";
            }
            if ($event['locationElementId'] && $event['locationElementId'] !== $element['uuid']) {
                $locationResult = $db->prepare("SELECT name FROM tl_gutesio_data_element WHERE uuid=?")->execute($event['locationElementId'])->fetchAssoc();
                if ($locationResult && isset($locationResult['name'])) {
                    $locRaw = html_entity_decode((string)$locationResult['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $location = strip_tags($locRaw);
                } else {
                    $location = '';
                }
            }
        }

        // Restore legacy ImageCache handling for child/event image
        // Normalize unexpected directory segment (e.g., "/images/events/..." -> "/images/offers/{uuid}/...")
        $offerSrc = '';
        if (!empty($child['imageCDN'])) {
            $normalizedCdnPath = $this->normalizeOfferImageCdnPath((string)$child['imageCDN'], (string)$child['uuid']);
            if (($this->model->gutesio_banner_strict_images === '1' || $this->model->gutesio_banner_strict_images === 1)) {
                $offerSrc = $fileUtils->addUrlToPathAndGetImageStrict($cdnUrl, $normalizedCdnPath);
            } else {
                $offerSrc = $fileUtils->addUrlToPathAndGetImage($cdnUrl, $normalizedCdnPath);
            }
        }
        // Normalize and sanitize child/title/contact texts (decode HTML entities first to avoid showing &#34; etc.)
        $childNameRaw = html_entity_decode((string)($child['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeChildName = strip_tags($childNameRaw);
        $childShortRaw = html_entity_decode((string)($child['shortDescription'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeShort = strip_tags($childShortRaw);
        $contactRaw = html_entity_decode((string)($element['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeContact = strip_tags($contactRaw);

        // Bild-Slide nur erstellen, wenn ein valides Bild vorhanden ist. Videos sollen unabhängig davon angezeigt werden.
        $canAddImageSlide = true;
        if (($this->model->gutesio_banner_strict_images === '1' || $this->model->gutesio_banner_strict_images === 1) && empty($offerSrc)) {
            $canAddImageSlide = false; // in Strict-Mode keine Bild-Slide ohne lokales/valides Bild
        }
        if ($offerSrc && strpos($offerSrc, '/default/') !== false) {
            $canAddImageSlide = false; // Default-Platzhalter nicht als Bild-Slide anzeigen
        }
        $detailPage = $type['type'] . "DetailPage";
        $detailRoute =  C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->$detailPage . '::absolute}}') . '/' . trim($child['uuid'],'{}');

        if ($canAddImageSlide) {
            $singleEle = [
                'type'  => "event",
                'image' => [
                    'src' =>    $offerSrc,
                    'alt' =>    $safeChildName
                ],
                'dateTime' => $termin,
                'location' => $location,
                'title' => $safeChildName,
                'slogan' => $safeShort,
                'href' => $detailRoute,
                'contact' => $safeContact,
                'qrcode' => base64_encode($this->generateQrCode($detailRoute))
            ];
            if ($logoSrc) {
                $singleEle['logo'] = [
                    'src' => $logoSrc,
                    'alt' => $element['name']
                ];
            }
            $arrReturn[] = $singleEle;
        }

        // Zusätzliches Video-Slide für Kinder mit VideoLink (MP4/YouTube), wenn im Modul aktiviert
        try {
            $playVideos  = ($this->model->gutesio_banner_play_videos === '1' || $this->model->gutesio_banner_play_videos === 1);
        } catch (\Throwable $t) { $playVideos = false; }
        if ($playVideos) {
            $videoLink = trim((string)($child['videoLink'] ?? ''));
            $videoType = strtolower(trim((string)($child['videoType'] ?? '')));
            if ($videoLink !== '') {
                $urlPath = parse_url($videoLink, PHP_URL_PATH) ?: '';
                $isMp4 = is_string($urlPath) && (strtolower(pathinfo($urlPath, PATHINFO_EXTENSION)) === 'mp4');
                if ($videoType === 'mp4' || $isMp4) {
                    $videoSlide = [
                        'type'  => 'event',
                        'video' => [ 'src' => $videoLink ],
                        'dateTime' => $termin,
                        'location' => $location,
                        'title' => $safeChildName,
                        'slogan' => $safeShort,
                        'href' => $detailRoute,
                        'contact' => $safeContact,
                        // QR-Code für Videos hinzufügen (Detailseite)
                        'qrcode' => base64_encode($this->generateQrCode($detailRoute)),
                    ];
                    if ($logoSrc) {
                        $videoSlide['logo'] = [ 'src' => $logoSrc, 'alt' => $element['name'] ];
                    }
                    $arrReturn[] = $videoSlide;
                } elseif ($videoType === 'youtube' || stripos($videoLink, 'youtube') !== false || stripos($videoLink, 'youtu.be') !== false) {
                    $ytId = $this->extractYouTubeId($videoLink);
                    if ($ytId) {
                        // In Kiosk/Chromium-Modus versuchen wir, mit Ton zu starten (mute=0).
                        // Fallbacks werden clientseitig gehandhabt, falls der Browser blockt.
                        try {
                            $kiosk = ($this->model->gutesio_kiosk_mode === '1' || $this->model->gutesio_kiosk_mode === 1);
                        } catch (\Throwable $t) { $kiosk = false; }
                        $mute = $kiosk ? '0' : '1';
                        // Ergänzte Player-Parameter für stabileres Autoplay ohne Endscreen/Interaktionen
                        // - fs=0 (kein Vollbild-Button)
                        // - disablekb=1 (Tastatur deaktivieren)
                        // - iv_load_policy=3 (Annotations/Overlays aus)
                        $ytSrc = sprintf(
                            'https://www.youtube-nocookie.com/embed/%s?autoplay=1&mute=%s&playsinline=1&loop=1&playlist=%s&controls=0&modestbranding=1&rel=0&enablejsapi=1&fs=0&disablekb=1&iv_load_policy=3',
                            $ytId,
                            $mute,
                            $ytId
                        );
                        $ytSlide = [
                            'type'    => 'event',
                            'youtube' => [ 'src' => $ytSrc, 'id' => $ytId ],
                            'dateTime' => $termin,
                            'location' => $location,
                            'title' => $safeChildName,
                            'slogan' => $safeShort,
                            'href' => $detailRoute,
                            'contact' => $safeContact,
                            // QR-Code für Videos hinzufügen (Detailseite)
                            'qrcode' => base64_encode($this->generateQrCode($detailRoute)),
                        ];
                        if ($logoSrc) {
                            $ytSlide['logo'] = [ 'src' => $logoSrc, 'alt' => $element['name'] ];
                        }
                        $arrReturn[] = $ytSlide;
                    }
                }
            }
        }

        return $arrReturn;
    }

    /**
     * Some instances provide child image CDN paths under an "events" directory that does not exist in the cache.
     * Expected structure is "/images/offers/{uuid}/filename.ext".
     * This helper rewrites known legacy/mistaken patterns to the expected "offers/{uuid}" pattern.
     */
    private function normalizeOfferImageCdnPath(string $path, string $childUuid): string
    {
        try {
            $uuid = trim($childUuid, '{}');
            if ($uuid === '') { return $path; }

            $parts = parse_url($path);
            // Absolute external URLs (http/https) should be left untouched
            if (is_array($parts) && isset($parts['scheme']) && ($parts['scheme'] === 'http' || $parts['scheme'] === 'https')) {
                return $path;
            }

            $onlyPath = is_array($parts) && isset($parts['path']) ? $parts['path'] : $path;

            // If already in offers with a uuid segment, leave as-is
            if (preg_match('~^/images/offer[s]?/[^/]+/.+~i', $onlyPath)) {
                return $path;
            }

            // Replace "/images/events/{something}/..." with "/images/offers/{uuid}/..."
            if (preg_match('~^/images/events/[^/]+/.+~i', $onlyPath)) {
                $rewritten = preg_replace('~^/images/events/[^/]+/~i', '/images/offers/' . $uuid . '/', $onlyPath, 1);
                // Reassemble with original query if present
                if (is_array($parts) && isset($parts['query']) && $parts['query'] !== '') {
                    $rewritten .= '?' . $parts['query'];
                }
                return $rewritten;
            }

            // Replace "/events/{something}/..." (missing leading "/images") with "/images/offers/{uuid}/..."
            if (preg_match('~^/events/[^/]+/.+~i', $onlyPath)) {
                // Keep only the basename to avoid nesting unexpected subfolders under the UUID
                $basename = basename($onlyPath);
                $rewritten = '/images/offers/' . $uuid . '/' . $basename;
                if (is_array($parts) && isset($parts['query']) && $parts['query'] !== '') {
                    $rewritten .= '?' . $parts['query'];
                }
                return $rewritten;
            }

            // Replace "/offers/{something}/..." (missing "/images" prefix) with "/images/offers/{uuid}/..."
            if (preg_match('~^/offers/[^/]+/.+~i', $onlyPath)) {
                $basename = basename($onlyPath);
                $rewritten = '/images/offers/' . $uuid . '/' . $basename;
                if (is_array($parts) && isset($parts['query']) && $parts['query'] !== '') {
                    $rewritten .= '?' . $parts['query'];
                }
                return $rewritten;
            }

            // If path is missing the uuid segment but contains "/images/offers/", inject the expected uuid
            if (preg_match('~^/images/offers/(?:[^/]+)?/?([^/]+)?$~i', $onlyPath)) {
                // Build a safe fallback: put the file under the uuid directory
                $basename = basename($onlyPath);
                $dir = '/images/offers/' . $uuid . '/';
                $rewritten = $dir . $basename;
                if (is_array($parts) && isset($parts['query']) && $parts['query'] !== '') {
                    $rewritten .= '?' . $parts['query'];
                }
                return $rewritten;
            }

            return $path;
        } catch (\Throwable $t) {
            return $path; // fail safe
        }
    }
    /**
     * Build a QR code PNG (binary string) for the given link using the module's theme color
     * as the foreground color. Falls back to the classic blue if the color is not available.
     */
    private function generateQrCode (String $link) {
        try {
            $eye = SquareEye::instance();
            $squareModule = SquareModule::instance();

            // Resolve theme accent color from module, accept values with or without '#'
            $hex = '';
            if ($this->model) {
                $hex = trim((string)($this->model->gutesio_banner_theme_color ?? ''));
            }
            if ($hex === '' || $hex[0] !== '#') {
                $hex = ($hex !== '') ? ('#'.$hex) : '';
            }
            // Default blue if invalid/missing
            if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
                $hex = '#2ea1db';
            }
            // Normalize to 6-digit
            if (strlen($hex) === 4) {
                $hex = sprintf('#%1$s%1$s%2$s%2$s%3$s%3$s', $hex[1], $hex[2], $hex[3]);
            }
            $r = hexdec(substr($hex, 1, 2));
            $g = hexdec(substr($hex, 3, 2));
            $b = hexdec(substr($hex, 5, 2));

            // Create a slightly darker variant for the gradient to keep visual depth
            $darken = function(int $c, float $f): int { $v = (int) floor($c * $f); return max(0, min(255, $v)); };
            $dr = $darken($r, 0.55);
            $dg = $darken($g, 0.55);
            $db = $darken($b, 0.55);

            $eyeFill = new EyeFill(new Rgb($r, $g, $b), new Rgb($r, $g, $b));
            // Foreground gradient uses darker tone to enrich modules; background stays white for scan contrast
            $gradient = new Gradient(new Rgb($dr, $dg, $db), new Rgb($dr, $dg, $db), GradientType::HORIZONTAL());

            $renderer = new ImageRenderer(
                new RendererStyle(
                    400, // size
                    2,   // margin (quiet zone)
                    $squareModule,
                    $eye,
                    Fill::withForegroundGradient(new Rgb(255, 255, 255), $gradient, $eyeFill, $eyeFill, $eyeFill)
                ),
                new ImagickImageBackEnd('png')
            );

            $writer = new Writer($renderer);
            return $writer->writeString($link);
        } catch (\Throwable $e) {
            // Fallback: render with safe default colors if anything goes wrong
            $eye = SquareEye::instance();
            $squareModule = SquareModule::instance();
            $eyeFill = new EyeFill(new Rgb(0, 0, 0), new Rgb(0, 0, 0));
            $gradient = new Gradient(new Rgb(0, 0, 0), new Rgb(0, 0, 0), GradientType::HORIZONTAL());
            $renderer = new ImageRenderer(
                new RendererStyle(400, 2, $squareModule, $eye, Fill::withForegroundGradient(new Rgb(255,255,255), $gradient, $eyeFill, $eyeFill, $eyeFill)),
                new ImagickImageBackEnd('png')
            );
            $writer = new Writer($renderer);
            return $writer->writeString($link);
        }
    }
}