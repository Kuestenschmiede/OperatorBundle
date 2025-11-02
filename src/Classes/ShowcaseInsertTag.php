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

/**
 * Class ShowcaseInsertTag
 * @package gutesio\OperatorBundle\Classes
 */
class ShowcaseInsertTag
{
    const TAG = 'showcase';

    const TAG_PAYLOAD = ['name', 'longitude', 'latitude', 'city', 'link', 'image', 'imageCDN', 'imageList', 'logo', 'previewimage', 'description', 'meta', 'canonical', 'count'];

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

                if ($field == 'count') {
                    $rows = Database::getInstance()->prepare('SELECT COUNT(*) AS rowCount FROM tl_gutesio_data_element')->execute()->fetchAssoc();
                    return strval($rows['rowCount']);
                }
            } else {
                $alias = $this->getAlias();
                $field = $arrTags[1];
                if ($field == 'count') {
                    $rows = Database::getInstance()->prepare('SELECT COUNT(*) AS rowCount FROM tl_gutesio_data_element')->execute()->fetchAssoc();
                    return strval($rows['rowCount']);
                }
            }

            $objSettings = GutesioOperatorSettingsModel::findSettings();
            $cdnUrl = $objSettings->cdnUrl;

            $fileUtils = new FileUtils();

            if ($alias) {
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
                        $showcaseUrl = C4GUtils::replaceInsertTags('{{link_url::' . $objSettings->showcaseDetailPage . '}}');
                        $url = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/' . $showcaseUrl . '/' . $alias;

                        return '{{link_open::'.$url.'}}'.html_entity_decode($arrShowcase['name']).'{{link_close}}';
                    case 'image':
                        $url = $arrShowcase['imageCDN'];
                        return $url ? $fileUtils->addUrlToPathAndGetImage($cdnUrl,$url, '-small',600) : ''; //Further processing in the template
                    case 'imageCDN':
                        $url = $arrShowcase['imageCDN'];
                        return $url ? $fileUtils->addUrlToPathAndGetImage($cdnUrl,$url,'',2400,660) : '';
                    case 'imageList':
                        $url = $arrShowcase['imageCDN'];
                        return $url ? $fileUtils->addUrlToPathAndGetImage($cdnUrl,$url, '-small',600) : '';
                    case 'previewimage':
                        $url = $arrShowcase['imageCDN'];
                        return $url ? $fileUtils->addUrlToPathAndGetImage($cdnUrl,$url,'',2400,660) : '';
                    case 'logo':
                        $url = $arrShowcase['logoCDN'];
                        return $url ? $fileUtils->addUrlToPathAndGetImage($cdnUrl,$url) : '';
                        return C4GUtils::truncate($arrShowcase['description'], 275);
                    case 'meta':
                        $metaDescription = $arrShowcase['metaDescription'];
                        if ($metaDescription) {
                            $logo = $arrShowcase['logoCDN'];
                            if ($logo && $cdnUrl) {
                                $logoPath = $fileUtils->addUrlToPathAndGetImage($cdnUrl, $logo);
                                $metaDescription = str_replace('IO_SHOWCASE_LOGO', $logoPath, $metaDescription);
                            } else {
                                $metaDescription = str_replace(',"logo":"IO_SHOWCASE_LOGO"', '', $metaDescription);
                            }
                            $image = $arrShowcase['imageCDN'];
                            if ($image && $cdnUrl) {
                                $imagePath = $fileUtils->addUrlToPathAndGetImage($cdnUrl, $image, '-meta', 1200, 630);
                                $metaDescription = str_replace('IO_SHOWCASE_IMAGE', $imagePath, $metaDescription);
                            } else {
                                $metaDescription = str_replace(',"image":"IO_SHOWCASE_IMAGE"', '', $metaDescription);
                            }

                            //replace gallery dummy
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
