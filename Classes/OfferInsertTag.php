<?php


namespace gutesio\OperatorBundle\Classes;


use Contao\Controller;
use Contao\Database;
use Contao\StringUtil;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

class OfferInsertTag
{
    const TAG = "offer";
    
    const TAG_PAYLOAD = ["description", "firstGalleryImage", "name", "meta"];

    //ToDO -> Core
    private function isBinary($str) {

        $umlauts = explode(",", "Ŕ,Á,Â,Ă,Ä,Ĺ,Ç,Č,É,Ę,Ë,Ě,Í,Î,Ď,Ň,Ó,Ô,Ő,Ö,Ř,Ů,Ú,Ű,Ü,Ý,ŕ,á,â,ă,ä,ĺ,ç,č,é,ę,ë,ě,í,î,ď,đ,ň,ó,ô,ő,ö,ř,ů,ú,ű,ü,ý,˙,Ń,ń,ß");
        foreach($umlauts as $umlaut){
            if (false !== (strpos($str, $umlaut))){
                return false;
            }
        }

        if (preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0) {
            return preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0;
        }
    }

    //ToDO -> Core
    private function truncate($text, $length) {
        $text = strip_tags($text);
        $length = abs((int)$length);
        $firstFullstop = strpos($text, ".");
        if ($firstFullstop && $firstFullstop <= ($length-1)) {
            return substr($text, 0, $firstFullstop);
        }
        if(strlen($text) > $length) {
            $text = preg_replace("/^(.{1,$length})(\s.*|$)/s", '\\1...', $text);
        }
        return($text);
    }
    
    /**
     * Replaces Insert tags for showcases. The insert tag is expected to have the following format:
     * {{showcase::name||image||logo||previewimage}}
     * @param string $insertTag
     * @return string|bool
     */
    public function replaceShowcaseTags(string $insertTag)
    {
        $arrTags = explode("::", $insertTag);
        if (count($arrTags) === 2 &&
            $arrTags[0] === self::TAG &&
            in_array($arrTags[1], self::TAG_PAYLOAD)
        ) {
            // get alias
            $alias = $this->getAlias();
            $alias = "{".strtoupper($alias)."}";
            $objOffer = Database::getInstance()->prepare("SELECT * FROM tl_gutesio_data_child WHERE `uuid` = ?")
                ->execute($alias);
            $arrOffer = $objOffer->fetchAllAssoc();
            if ($arrOffer) {
                $arrOffer = $arrOffer[0];
                switch ($arrTags[1]) {
                    case "name":
                        return $arrOffer['name'];
                    case "description":
                        return $this->truncate($arrOffer['description'], 150);
                    case "firstGalleryImage":
                        $arrBin = StringUtil::deserialize($arrOffer['imageGallery']);

                        $uuid = $arrBin[0];
                        if ($this->isBinary($uuid)) {
                            $uuid = StringUtil::binToUuid($uuid);
                        }

                        return $uuid ?: ""; //Further processing in the template
                    case "meta":
                        $metaDescription = $arrOffer['metaDescription'];
                        if ($metaDescription) {
                            $pageURL = \Contao\Controller::replaceInsertTags("{{env::url}}");

                            //replace image dummy
                            $uuid = $arrOffer['imageOffer']; //ToDo Test
                            if ($this->isBinary($uuid)) {
                                $uuid = StringUtil::binToUuid($uuid);
                            }
                            $image = Controller::replaceInsertTags("{{file::$uuid}}");
                            if ($image && $pageURL) {
                                $imagePath = $pageURL.'/'.$image;
                                $metaDescription = str_replace('IO_OFFER_IMAGE', $imagePath, $metaDescription);
                            } else {
                                $metaDescription = str_replace(',"image":"IO_OFFER_IMAGE"','', $metaDescription);
                            }

                            //replace gallery dummy
                            //ToDO
                            $metaDescription = str_replace(',"photo":"IO_OFFER_PHOTO"','', $metaDescription);

                            //replace url dummy
                            $url = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                            $metaDescription = str_replace('IO_OFFER_URL',$url, $metaDescription);

                            //replace showcase params
                            $offerConnections = Database::getInstance()->prepare("SELECT elementId FROM tl_gutesio_data_child_connection WHERE childId = ?")
                                ->execute($arrOffer['uuid'])->fetchAllAssoc();
                            if ($offerConnections and (count($offerConnections) > 0)) {
                                $firstConnection = $offerConnections[0];
                                $objShowcase = Database::getInstance()->prepare("SELECT * FROM tl_gutesio_data_element WHERE `uuid` = ?")
                                    ->execute($firstConnection['elementId'])->fetchAssoc();

                                if ($objShowcase) {
                                    //replace logo dummy
                                    $uuid = $objShowcase['logo'];
                                    if ($this->isBinary($uuid)) {
                                        $uuid = StringUtil::binToUuid($uuid);
                                    }
                                    $logo = Controller::replaceInsertTags("{{file::$uuid}}");
                                    if ($logo && $pageURL) {
                                        $logoPath = $pageURL.'/'.$logo;
                                        $metaDescription = str_replace('IO_SHOWCASE_LOGO',$logoPath, $metaDescription);
                                    } else {
                                        $metaDescription = str_replace(',"logo":"IO_SHOWCASE_LOGO"','', $metaDescription);
                                    }

                                    //replace image dummy
                                    $uuid = $objShowcase['imageList'];
                                    if ($this->isBinary($uuid)) {
                                        $uuid = StringUtil::binToUuid($uuid);
                                    }
                                    $image = Controller::replaceInsertTags("{{file::$uuid}}");
                                    if ($image && $pageURL) {
                                        $imagePath = $pageURL.'/'.$image;
                                        $metaDescription = str_replace('IO_SHOWCASE_IMAGE',$imagePath, $metaDescription);
                                    } else {
                                        $metaDescription = str_replace(',"image":"IO_SHOWCASE_IMAGE"','', $metaDescription);
                                    }

                                    //replace gallery dummy
                                    //ToDO
                                    $metaDescription = str_replace(',"photo":"IO_SHOWCASE_PHOTO"','', $metaDescription);

                                    //replace url dummy
                                    $objSettings = GutesioOperatorSettingsModel::findSettings();
                                    $showcaseUrl = Controller::replaceInsertTags("{{link_url::".$objSettings->showcaseDetailPage."}}");
                                    $url = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/'.$showcaseUrl.'/'.$objShowcase['alias'];
                                    $metaDescription = str_replace('IO_SHOWCASE_URL',$url, $metaDescription);
                                }
                            }

                            return $metaDescription;
                        }
                        break;
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
        $currentUrl = $_SERVER["REQUEST_URI"];
        // remove query string, if it exists
        if (($pos = strpos($currentUrl, "?")) !== false) {
            $currentUrl = substr($currentUrl, 0, $pos);
        }
        $indexOfLastSlash = strrpos($currentUrl, "/", -1);
        // pos +1 because we want to strip the /
        $alias = substr($currentUrl, $indexOfLastSlash + 1);
        
        return $alias;
    }
}