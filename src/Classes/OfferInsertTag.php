<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
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
        $insertTag = str_replace(['{{', '}}'], '', $insertTag);
        $arrTags = explode('::', $insertTag);
        $fileUtils = new FileUtils();

        if (
            (count($arrTags) === 2 && ($arrTags[0] === self::TAG) && (in_array($arrTags[1], self::TAG_PAYLOAD)) ) ||
            (count($arrTags) === 3 && ($arrTags[0] === self::TAG) && (in_array($arrTags[2], self::TAG_PAYLOAD)) )
        ) {
            // get alias
            if (count($arrTags) === 3) {
                $alias = $arrTags[1] ?: $this->getAlias();
                $field = $arrTags[2];
            } else {
                $alias = $this->getAlias();
                $field = $arrTags[1];
            }

            $objSettings = GutesioOperatorSettingsModel::findSettings();
            $cdnUrl = $objSettings->cdnUrl;

            $uuidAlias = $alias;
            if ($uuidAlias && $uuidAlias[0] !== '{') {
                $uuidAlias = '{' . strtoupper($uuidAlias) . '}';
            } else {
                $uuidAlias = strtoupper($uuidAlias);
            }
            $objOffer = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_child WHERE `uuid` = ? OR `alias` = ? ORDER BY metaDescription DESC, tstamp DESC')
                ->execute($uuidAlias, $alias);
            $arrOffer = $objOffer->fetchAllAssoc();
            if (!$arrOffer) {
                // Try encoded alias
                $objOffer = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_child WHERE `alias` = ? ORDER BY metaDescription DESC, tstamp DESC')
                    ->execute(rawurlencode($alias));
                $arrOffer = $objOffer->fetchAllAssoc();
            }
            if (!$arrOffer) {
                // Try decoded alias (just in case)
                $objOffer = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_child WHERE `alias` = ? ORDER BY metaDescription DESC, tstamp DESC')
                    ->execute(urldecode($alias));
                $arrOffer = $objOffer->fetchAllAssoc();
            }
            if (!$arrOffer) {
                // Try case-insensitive alias search if not found
                $objOffer = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_child WHERE `alias` = ? ORDER BY metaDescription DESC, tstamp DESC')
                    ->execute(strtolower($alias));
                $arrOffer = $objOffer->fetchAllAssoc();
            }
            if (!$arrOffer) {
                // Try case-insensitive and encoded
                $objOffer = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_child WHERE `alias` = ? ORDER BY metaDescription DESC, tstamp DESC')
                    ->execute(strtolower(rawurlencode($alias)));
                $arrOffer = $objOffer->fetchAllAssoc();
            }
            if ($arrOffer) {
                $arrOffer = $arrOffer[0];
                switch ($field) {
                    case 'name':
                        return html_entity_decode($arrOffer['name']);
                    case 'description':
                        return C4GUtils::truncate($arrOffer['description'], 275) ?: '';
                    case 'firstGalleryImage':
                        $arrUrls = StringUtil::deserialize($arrOffer['imageGalleryCDN']);

                        /*if ($arrUrls && is_array($arrUrls) && count($arrUrls)) {
                            $url = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrUrls[0]);
                        } else {*/
                            $url = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrOffer['imageCDN']);
                        //}
                        $result = $fileUtils->getImageSizeAndOrientation($url);
                        $orientation = $result[1];

                        if ($orientation === 'landscape') {
                            $width = 1040;
                            $height = 690;
                        } else {
                            $width = 690;
                            $height = 1040;
                        }

                        /*if ($arrUrls && is_array($arrUrls) && count($arrUrls)) {
                            $url = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrUrls[0], '', $width, $height);
                        } else {*/
                            $url = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrOffer['imageCDN'], '', $width, $height);
                        //}

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

                            // Clean up remaining dummies to ensure valid JSON
                            $metaDescription = str_replace([
                                ',"addContext":"https://schema.org"',
                                '"addContext":"https://schema.org",',
                                '"addContext":"https://schema.org"',
                                ',"image":"IO_OFFER_IMAGE"',
                                '"image":"IO_OFFER_IMAGE",',
                                '"image":"IO_OFFER_IMAGE"',
                                ',"url":"IO_OFFER_URL"',
                                '"url":"IO_OFFER_URL",',
                                '"url":"IO_OFFER_URL"',
                                ',"image":"IO_SHOWCASE_IMAGE"',
                                '"image":"IO_SHOWCASE_IMAGE",',
                                '"image":"IO_SHOWCASE_IMAGE"',
                                ',"url":"IO_SHOWCASE_URL"',
                                '"url":"IO_SHOWCASE_URL",',
                                '"url":"IO_SHOWCASE_URL"',
                                ',"location":"IO_SHOWCASE_LOCATION_URL"',
                                '"location":"IO_SHOWCASE_LOCATION_URL",',
                                '"location":"IO_SHOWCASE_LOCATION_URL"',
                                ',"logo":"IO_SHOWCASE_LOGO"',
                                '"logo":"IO_SHOWCASE_LOGO",',
                                '"logo":"IO_SHOWCASE_LOGO"',
                                ',"photo":"IO_SHOWCASE_PHOTO"',
                                '"photo":"IO_SHOWCASE_PHOTO",',
                                '"photo":"IO_SHOWCASE_PHOTO"',
                                ',"photo":"IO_OFFER_PHOTO"',
                                '"photo":"IO_OFFER_PHOTO",',
                                '"photo":"IO_OFFER_PHOTO"'
                            ], '', $metaDescription);

                            $metaDescription = str_replace([
                                'IO_OFFER_IMAGE',
                                'IO_OFFER_URL',
                                'IO_OFFER_PHOTO',
                                'IO_SHOWCASE_IMAGE',
                                'IO_SHOWCASE_LOCATION_URL',
                                'IO_SHOWCASE_LOGO',
                                'IO_SHOWCASE_PHOTO',
                                'IO_SHOWCASE_URL'
                            ], '', $metaDescription);

                            return html_entity_decode($metaDescription, ENT_NOQUOTES, 'UTF-8');
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

    public static function getAliasStatic()
    {
        $currentUrl = $_SERVER['REQUEST_URI'];
        // remove query string, if it exists
        if (($pos = strpos($currentUrl, '?')) !== false) {
            $currentUrl = substr($currentUrl, 0, $pos);
        }

        $currentUrl = trim($currentUrl, '/');
        if ($currentUrl === '') {
            return '';
        }

        $parts = explode('/', $currentUrl);
        $alias = end($parts);

        if (str_ends_with($alias, '.html')) {
            $alias = substr($alias, 0, -5);
        }

        return urldecode($alias);
    }

    private function getAlias()
    {
        return self::getAliasStatic();
    }
}
