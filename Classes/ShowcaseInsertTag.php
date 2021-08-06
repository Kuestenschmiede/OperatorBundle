<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes;

use Contao\Controller;
use Contao\Database;
use Contao\StringUtil;

/**
 * Class ShowcaseInsertTag
 * @package gutesio\OperatorBundle\Classes
 */
class ShowcaseInsertTag
{
    const TAG = 'showcase';

    const TAG_PAYLOAD = ['name', 'image','imageList', 'logo', 'previewimage', 'description', 'meta', 'canonical'];

    //ToDO -> Core
    private function isBinary($str)
    {
        $umlauts = explode(',', 'Ŕ,Á,Â,Ă,Ä,Ĺ,Ç,Č,É,Ę,Ë,Ě,Í,Î,Ď,Ň,Ó,Ô,Ő,Ö,Ř,Ů,Ú,Ű,Ü,Ý,ŕ,á,â,ă,ä,ĺ,ç,č,é,ę,ë,ě,í,î,ď,đ,ň,ó,ô,ő,ö,ř,ů,ú,ű,ü,ý,˙,Ń,ń,ß');
        foreach ($umlauts as $umlaut) {
            if (false !== (strpos($str, $umlaut))) {
                return false;
            }
        }

        if (preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0) {
            return preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0;
        }
    }

    //ToDO -> Core
    private function truncate($text, $length)
    {
        $text = str_replace('><', '> <', $text);
        $text = strip_tags($text);
        $text = htmlspecialchars($text, ENT_QUOTES, "utf-8");
        $length = abs((int) $length);
        $firstFullstop = strpos($text, '.');
        if ($firstFullstop && $firstFullstop <= ($length - 1)) {
            for ($i = 0, $j = strlen($text); $i < $j; $i++) {
                if ((strstr('.',$text[$i])) && ($i <= ($length -1))) {
                    $firstFullstop = $i;
                }
            }
            return substr($text, 0, $firstFullstop+1);
        }
        if (strlen($text) > $length) {
            $text = preg_replace("/^(.{1,$length})(\s.*|$)/s", '\\1...', $text);
        }

        return(trim($text));
    }

    /**
     * Replaces Insert tags for showcases. The insert tag is expected to have the following format:
     * {{showcase::name||image||logo||previewimage}}
     * @param string $insertTag
     * @return string|bool
     */
    public function replaceShowcaseTags(string $insertTag)
    {
        $arrTags = explode('::', $insertTag);
        if (count($arrTags) === 2 &&
            $arrTags[0] === self::TAG &&
            in_array($arrTags[1], self::TAG_PAYLOAD)
        ) {
            // get alias
            $alias = $this->getAlias();
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
                switch ($arrTags[1]) {
                    case 'name':
                        return $arrShowcase['name'];
                    case 'image':
                        $uuid = $arrShowcase['imageShowcase'];
                        if ($this->isBinary($uuid)) {
                            $uuid = StringUtil::binToUuid($uuid);
                        }

                        return $uuid ?: ''; //Further processing in the template

                    case 'imageList':
                        $uuid = $arrShowcase['imageList'];
                        if ($this->isBinary($uuid)) {
                            $uuid = StringUtil::binToUuid($uuid);
                        }

                        return $uuid ?: ''; //Further processing in the template

                    case 'previewimage':
                        $uuid = $arrShowcase['image'];
                        if ($this->isBinary($uuid)) {
                            $uuid = StringUtil::binToUuid($uuid);
                        }

                        return $uuid ?: '';//Controller::replaceInsertTags("{{image::$uuid}}");
                    case 'logo':
                        $uuid = $arrShowcase['logo'];
                        if ($this->isBinary($uuid)) {
                            $uuid = StringUtil::binToUuid($uuid);
                        }

                        return Controller::replaceInsertTags("{{image::$uuid?height=150&mode=proportional&class=img-fluid}}");
                    case 'description':
                        return $this->truncate($arrShowcase['description'], 150);
                    case 'meta':
                        $metaDescription = $arrShowcase['metaDescription'];
                        if ($metaDescription) {
                            $pageURL = \Contao\Controller::replaceInsertTags('{{env::url}}');

                            //replace logo dummy
                            $uuid = $arrShowcase['logo'];
                            if ($this->isBinary($uuid)) {
                                $uuid = StringUtil::binToUuid($uuid);
                            }
                            $logo = Controller::replaceInsertTags("{{file::$uuid}}");
                            if ($logo && $pageURL) {
                                $logoPath = $pageURL . '/' . $logo;
                                $metaDescription = str_replace('IO_SHOWCASE_LOGO', $logoPath, $metaDescription);
                            } else {
                                $metaDescription = str_replace(',"logo":"IO_SHOWCASE_LOGO"', '', $metaDescription);
                            }

                            //replace image dummy
                            $uuid = $arrShowcase['imageList'];
                            if ($this->isBinary($uuid)) {
                                $uuid = StringUtil::binToUuid($uuid);
                            }
                            $image = Controller::replaceInsertTags("{{file::$uuid}}");
                            if ($image && $pageURL) {
                                $imagePath = $pageURL . '/' . $image;
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

                            return $metaDescription;
                        }

                        break;
                    case 'canonical':
                        $currentUrl = $_SERVER['REQUEST_URI'];
                        // remove query string, if it exists
                        if (($pos = strpos($currentUrl, '?')) !== false) {
                            $currentUrl = substr($currentUrl, 0, $pos);
                        }

                        $currentUrl = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $currentUrl;


                        return '<link rel="canonical" href="'.$currentUrl.'" />';
                    default:
                        return false;
                }
            } else {
                return false;
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
