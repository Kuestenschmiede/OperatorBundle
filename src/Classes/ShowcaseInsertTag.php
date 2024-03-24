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
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

/**
 * Class ShowcaseInsertTag
 * @package gutesio\OperatorBundle\Classes
 */
class ShowcaseInsertTag
{
    const TAG = 'showcase';

    const TAG_PAYLOAD = ['name', 'longitude', 'latitude', 'city', 'link', 'image', 'imageList', 'logo', 'previewimage', 'description', 'meta', 'canonical'];

    /**
     * Replaces Insert tags for showcases. The insert tag is expected to have the following format:
     * {{showcase::name||image||logo||previewimage}}
     * @param string $insertTag
     * @return string|bool
     */
    public function replaceShowcaseTags(string $insertTag)
    {
        $arrTags = explode('::', $insertTag);
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

            $objShowcase = Database::getInstance()->prepare('SELECT * FROM tl_gutesio_data_element WHERE `alias` = ?')
                ->execute($alias);
            $arrShowcase = $objShowcase->fetchAssoc();
            if (empty($arrShowcase)) {
                $arrShowcase = Database::getInstance()->prepare(
                    'SELECT DISTINCT tl_gutesio_data_element.* FROM tl_gutesio_data_element ' .
                    'JOIN tl_gutesio_data_child_connection ON ' .
                    'tl_gutesio_data_child_connection.elementId = tl_gutesio_data_element.uuid ' .
                    'WHERE tl_gutesio_data_child_connection.childId = ?'
                )->execute('{' . $alias . '}')->fetchAssoc();
            }
            if ($arrShowcase) {
                switch ($field) {
                    case 'name':
                        return html_entity_decode($arrShowcase['name']);
                    case 'longitude':
                        return $arrShowcase['geox'];
                    case 'latitude':
                        return $arrShowcase['geoy'];
                    case 'city':
                        return $arrShowcase['locationCity'];
                    case 'link':
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $showcaseUrl = Controller::replaceInsertTags('{{link_url::' . $objSettings->showcaseDetailPage . '}}');
                        $url = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/' . $showcaseUrl . '/' . $alias;

                        return '{{link_open::'.$url.'}}'.html_entity_decode($arrShowcase['name']).'{{link_close}}';
                    case 'image':
                        //ToDO CDN
                        //ToDo CDN get params
                        //?crop=smart&width=400&height=400
                        $url = $arrShowcase['imageCDN'];
//                        if (C4GUtils::isBinary($uuid)) {
//                            $uuid = StringUtil::binToUuid($uuid);
//                        }

                        return $url ? $cdnUrl.$url : ''; //Further processing in the template
                    case 'imageList':
                        $url = $arrShowcase['imageCDN'];
//                        if (C4GUtils::isBinary($uuid)) {
//                            $uuid = StringUtil::binToUuid($uuid);
//                        }

                        return $url ? $cdnUrl.$url : ''; //Further processing in the template
                    case 'previewimage':
                        $url = $arrShowcase['imageCDN'];

                        return $url ? $cdnUrl.$url: '';//Controller::replaceInsertTags("{{image::$uuid}}");
                    case 'logo':
                        $url = $arrShowcase['logoCDN'];
//                        if (C4GUtils::isBinary($uuid)) {
//                            $uuid = StringUtil::binToUuid($uuid);
//                        }

                        return $url ? $cdnUrl.$url.'?height=150' : '';//Controller::replaceInsertTags("{{image::$uuid?height=150&mode=proportional&class=img-fluid}}");
                    case 'description':
                        return C4GUtils::truncate($arrShowcase['description'], 275);
                    case 'meta':
                        $metaDescription = $arrShowcase['metaDescription'];
                        if ($metaDescription) {
                            $pageURL = \Contao\Controller::replaceInsertTags('{{env::url}}');

                            //replace logo dummy
//                            $uuid = $arrShowcase['logo'];
//                            if (C4GUtils::isBinary($uuid)) {
//                                $uuid = StringUtil::binToUuid($uuid);
//                            }
//                            $logo = Controller::replaceInsertTags("{{file::$uuid}}");

                            $logo = $arrShowcase['logoCDN'];
                            if ($logo && $cdnUrl) {
                                $logoPath = $cdnUrl . $logo;
                                $metaDescription = str_replace('IO_SHOWCASE_LOGO', $logoPath, $metaDescription);
                            } else {
                                $metaDescription = str_replace(',"logo":"IO_SHOWCASE_LOGO"', '', $metaDescription);
                            }

                            //replace image dummy
//                            $uuid = $arrShowcase['imageList'];
//                            if (C4GUtils::isBinary($uuid)) {
//                                $uuid = StringUtil::binToUuid($uuid);
//                            }
//                            $image = Controller::replaceInsertTags("{{file::$uuid}}");
                            $image = $arrShowcase['imageCDN'];
                            if ($image && $cdnUrl) {
                                $imagePath = $cdnUrl . $image;
                                $metaDescription = str_replace('IO_SHOWCASE_IMAGE', $imagePath, $metaDescription);
                            } else {
                                $metaDescription = str_replace(',"image":"IO_SHOWCASE_IMAGE"', '', $metaDescription);
                            }

                            //replace gallery dummy
                            //ToDO
                            $metaDescription = str_replace(',"photo":"IO_SHOWCASE_PHOTO"', '', $metaDescription);

                            //replace url dummy
                            $url = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                            $metaDescription = str_replace('IO_SHOWCASE_URL', $url, $metaDescription);

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
