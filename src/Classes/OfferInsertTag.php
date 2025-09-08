<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes;

use con4gis\CoreBundle\Classes\C4GUtils;
use Contao\Controller;
use Contao\Database;
use Contao\StringUtil;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

class OfferInsertTag
{
    const TAG = 'offer';

    const TAG_PAYLOAD = ['description', 'firstGalleryImage', 'name', 'meta', 'canonical'];

    /**
     * Replaces Insert tags for showcases. The insert tag is expected to have the following format:
     * {{showcase::name||image||logo||previewimage}}
     * @param string $insertTag
     * @return string|bool
     */
    public function replaceShowcaseTags(string $insertTag)
    {
        $arrTags = explode('::', $insertTag);
        $fileUtils = new FileUtils();

        if (
            (count($arrTags) === 2 && ($arrTags[0] === self::TAG) && (in_array($arrTags[1], self::TAG_PAYLOAD)) ) ||
            (count($arrTags) === 3 && ($arrTags[0] === self::TAG) && (in_array($arrTags[2], self::TAG_PAYLOAD)) )
        ) {
            // get alias
            if (count($arrTags) === 3) {
                $alias = $arrTags[1];
                $field = $arrTags[2];
            } else {
                $alias = $this->getAlias();
                $field = $arrTags[1];
            }

            $objSettings = GutesioOperatorSettingsModel::findSettings();
            $cdnUrl = $objSettings->cdnUrl;

            $uuidAlias = '{' . strtoupper($alias) . '}';
            $objOffer = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_child WHERE `uuid` = ? OR `alias` = ? ')
                ->execute($uuidAlias, $alias);
            $arrOffer = $objOffer->fetchAllAssoc();
            if ($arrOffer) {
                $arrOffer = $arrOffer[0];
                switch ($field) {
                    case 'name':
                        return html_entity_decode($arrOffer['name']);
                    case 'description':
                        return C4GUtils::truncate($arrOffer['description'], 275) ?: '';
                    case 'firstGalleryImage':
                        $arrUrls = StringUtil::deserialize($arrOffer['imageGalleryCDN']);

                        if ($arrUrls && is_array($arrUrls) && count($arrUrls)) {
                            $url = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrUrls[0]);
                        } else {
                            $url = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrOffer['imageCDN']);
                        }
                        $result = $fileUtils->getImageSizeAndOrientation($url);
                        $orientation = $result[1];

                        if ($orientation === 'landscape') {
                            $width = 1040;
                            $height = 690;
                        } else {
                            $width = 690;
                            $height = 1040;
                        }

                        if ($arrUrls && is_array($arrUrls) && count($arrUrls)) {
                            $url = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrUrls[0], '', $width, $height);
                        } else {
                            $url = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrOffer['imageCDN'], '', $width, $height);
                        }

                        return $url ?: ''; //Further processing in the template
                    case 'meta':
                        $metaDescription = $arrOffer['metaDescription'];
                        if ($metaDescription) {
                            $pageURL = C4GUtils::replaceInsertTags('{{env::url}}');

                            //replace image dummy
//                            $uuid = $arrOffer['imageOffer']; //ToDo Test
//                            if (C4GUtils::isBinary($uuid)) {
//                                $uuid = StringUtil::binToUuid($uuid);
//                            }
                            $image = $arrOffer['imageCDN']; //ToDO CDN TEST
                            if ($image && $cdnUrl) {
                                $imagePath = $fileUtils->addUrlToPathAndGetImage($cdnUrl ,$image, '-meta',1200, 630);
                                $metaDescription = str_replace('IO_OFFER_IMAGE', $imagePath, $metaDescription);
                            } else {
                                $metaDescription = str_replace(',"image":"IO_OFFER_IMAGE"', '', $metaDescription);
                            }

                            //replace gallery dummy
                            //ToDO
                            $metaDescription = str_replace(',"photo":"IO_OFFER_PHOTO"', '', $metaDescription);

                            //replace url dummy
                            $url = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                            $metaDescription = str_replace('IO_OFFER_URL', $url, $metaDescription);

                            //replace showcase params
                            $offerConnections = Database::getInstance()->prepare('SELECT elementId FROM tl_gutesio_data_child_connection WHERE childId = ?')
                                ->execute($arrOffer['uuid'])->fetchAllAssoc();
                            if ($offerConnections and (count($offerConnections) > 0)) {
                                $firstConnection = $offerConnections[0];
                                $objShowcase = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_element WHERE `uuid` = ?')
                                    ->execute($firstConnection['elementId'])->fetchAssoc();

                                if ($objShowcase) {
                                    //replace logo dummy
//                                    $uuid = $objShowcase['logo'];
//                                    if (C4GUtils::isBinary($uuid)) {
//                                        $uuid = StringUtil::binToUuid($uuid);
//                                    }
                                    $logo = $objShowcase['logoCDN'];//  C4GUtils::replaceInsertTags("{{file::$uuid}}");
                                    if ($logo && $cdnUrl) {
                                        //ToDo CDN get params
                                        $logoPath = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$logo);
                                        $metaDescription = str_replace('IO_SHOWCASE_LOGO', $logoPath, $metaDescription);
                                    } else {
                                        $metaDescription = str_replace(',"logo":"IO_SHOWCASE_LOGO"', '', $metaDescription);
                                    }

                                    //replace image dummy
//                                    $uuid = $objShowcase['imageList'];
//                                    if (C4GUtils::isBinary($uuid)) {
//                                        $uuid = StringUtil::binToUuid($uuid);
//                                    }
                                    $image = $objShowcase['imageCDN'];//C4GUtils::replaceInsertTags("{{file::$uuid}}");
                                    if ($image && $cdnUrl) {
                                        $imagePath = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$image, '-meta', 1200, 630);
                                        $metaDescription = str_replace('IO_SHOWCASE_IMAGE', $imagePath, $metaDescription);
                                    } else {
                                        $metaDescription = str_replace(',"image":"IO_SHOWCASE_IMAGE"', '', $metaDescription);
                                    }

                                    //replace gallery dummy
                                    //ToDO
                                    $metaDescription = str_replace(',"photo":"IO_SHOWCASE_PHOTO"', '', $metaDescription);

                                    //replace url dummy
                                    $showcaseUrl = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->showcaseDetailPage . '}}');
                                    $url = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/' . $showcaseUrl . '/' . $objShowcase['alias'];
                                    $metaDescription = str_replace('IO_SHOWCASE_URL', $url, $metaDescription);

                                    if (strpos($metaDescription,'IO_SHOWCASE_LOCATION_URL')) {
                                        $locationElement = Database::getInstance()->prepare('SELECT locationElementId FROM tl_gutesio_data_child_event WHERE childId = ?')
                                            ->execute($arrOffer['uuid'])->fetchAssoc();
                                        if ($locationElement) {
                                            $locationElemenObject = Database::getInstance()->prepare('SELECT alias FROM tl_gutesio_data_element WHERE `uuid` = ?')
                                                ->execute($locationElement['locationElementId'])->fetchAssoc();
                                            if ($locationElemenObject && key_exists('alias', $locationElemenObject) && !empty($locationElemenObject['alias'])) {
                                                $locationUrl = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/' . $showcaseUrl . '/' . $locationElemenObject['alias'];
                                                $metaDescription = str_replace('IO_SHOWCASE_LOCATION_URL', $locationUrl, $metaDescription);
                                            }
                                        }

                                        //without locationElementId same showcase
                                        $metaDescription = str_replace('IO_SHOWCASE_LOCATION_URL', $url, $metaDescription);
                                    }
                                }
                            }

                            return html_entity_decode(htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'));
                        }

                        break;
                    case 'canonical':
                        $currentUrl = $_SERVER['REQUEST_URI'];
                        // remove query string, if it exists
                        if (($pos = strpos($currentUrl, '?')) !== false) {
                            $currentUrl = substr($currentUrl, 0, $pos);
                        }
                        $currentUrl = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $currentUrl;

                        return '<link rel="canonical" href="' . $currentUrl . '" />';
                    default:
                        return '';
                }
            } else {
                return '';
            }
        } else {
            return false;
        }
    }

    private function getAlias()
    {
        $currentUrl = $_SERVER['REQUEST_URI'];
        // remove query string, if it exists
        if (($pos = strpos($currentUrl, '?')) !== false) {
            $currentUrl = substr($currentUrl, 0, $pos);
        }
        $indexOfLastSlash = strrpos($currentUrl, '/', -1);
        // pos +1 because we want to strip the /
        $alias = substr($currentUrl, $indexOfLastSlash + 1);

        return $alias;
    }
}
