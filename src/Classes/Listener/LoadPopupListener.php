<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.
 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\FrameworkBundle\Classes\Utility\RegularExpression;
use con4gis\MapsBundle\Classes\Events\LoadInfoWindowEvent;
use Contao\Controller;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use gutesio\DataModelBundle\Classes\StringUtils;
use gutesio\DataModelBundle\Classes\TagFieldUtil;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class LoadPopupListener
{
    private $Database;

    /**
     * LayerContentService constructor.
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
        $this->Database = Database::getInstance();
    }

    public function onLoadPopupDoIt(
        LoadInfoWindowEvent $event,
        $eventName,
        EventDispatcherInterface $eventDispatcher
    ) {
        $popup = $event->getPopup();
        $requestString = $event->getPopupString();
        $reqParams = explode('::', $requestString);
        if ($reqParams[0] === 'showcase' && $reqParams[1]) {
            $objElement = $this->Database->prepare('SELECT * FROM tl_gutesio_data_element WHERE uuid = ?')->execute($reqParams[1])->fetchAssoc();
            $objSettingsModel = GutesioOperatorSettingsModel::findSettings();
            $url = Controller::replaceInsertTags('{{link_url::' . $objSettingsModel->showcaseDetailPage . '}}');
            $scope = $event->getScope();
            $strQueryTags = 'SELECT tag.uuid, tag.imageCDN, tag.name, tag.technicalKey FROM tl_gutesio_data_tag AS tag
                                    INNER JOIN tl_gutesio_data_tag_element AS elementTag ON elementTag.tagId = tag.uuid
                                    WHERE tag.published = 1 AND elementTag.elementId = ? ORDER BY tag.name ASC';
            $arrTags = $this->Database->prepare($strQueryTags)->execute($objElement['uuid'])->fetchAllAssoc();
            $popup = array_merge($this->getPopup($objElement, $url, $arrTags, $scope === "starboardscope"));
        }
        $event->setPopup($popup);
    }

    private function getPopup($element, $url, $arrTags, $reduced = false)
    {
        $name = $element['name'];
        $strQueryTypes = 'SELECT type.name FROM tl_gutesio_data_type AS type 
                                        INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.typeId = type.uuid
                                        WHERE typeElem.elementId = ?';
        $arrTypes = $this->Database->prepare($strQueryTypes)->execute($element['uuid'])->fetchAllAssoc();
        $strTypes = '';
        foreach ($arrTypes as $type) {
            $strTypes .= $type['name'] . ', ';
        }
        $strTypes = rtrim($strTypes, ', ');
        $file = $element['imageCDN'];
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;
        //$file = FilesModel::findByUuid($imageUuid) ? FilesModel::findByUuid($imageUuid) : FilesModel::findByUuid(StringUtil::binToUuid($element['image']));
        //$strImage = $file->path;
        if ($file) {
            $alt = $name;
            //?crop=smart&width=750&height=200
            $image = "<img class='entry-content' src='".StringUtils::addUrlToPath($cdnUrl,$file)."' alt='$alt' title='$name'>";
        }
        $tags = '';
        foreach ($arrTags as $tag) {
            $link = '';
            if ($tag['technicalKey']) {
                $tagFieldName = TagFieldUtil::getFieldnameForTechnicalKey($tag['technicalKey']);
                if (strpos($tagFieldName, 'Link') !== false) {
                    // is the tag field a link?
                    $strQueryTagValues = 'SELECT * FROM tl_gutesio_data_tag_element_values WHERE `elementId` = ? AND `tagFieldKey` = ?';
                    $arrTagValue = $this->Database->prepare($strQueryTagValues)->execute($element['uuid'], $tagFieldName)->fetchAssoc();
                    $link = $arrTagValue['tagFieldValue'];
                    if (!preg_match('/' . RegularExpression::URL . '/', $link)) {
                        $link = '';
                    } elseif (strpos($link, 'http') === false) {
                        $link = 'https://' . $link;
                    }
                }
            }


            $fileTag = $tag['imageCDN'] ? StringUtils::addUrlToPath($cdnUrl,$tag['imageCDN']) : false;
            if ($fileTag) {
                if ($link) {
                    $tags .= "<a href='" . $link . "'><div class='item " . $tag['name'] . "'>
                        <img class='entry-content " . $tag['name'] . "' src='" . $fileTag . "' alt='" . $tag['name'] . "' title='" . $tag['name'] . "'>
                        </div></a>";
                } else {
                    $tags .= "<div class='item " . $tag['name'] . "'>
                        <img class='entry-content " . $tag['name'] . "' src='" . $fileTag . "' alt='" . $tag['name'] . "' title='" . $tag['name'] . "'>
                        </div>";
                }
            }
        }
        $contacts = '';
        if ($element['phone'] || $element['mobile']) {
            $phone = $element['phone'] ?: $element['mobile'];
            $contacts .= "<a class='entry-content contact-phone' title='$name anrufen' href='tel:".str_replace(" ", "", str_replace("/", "", $phone))."'>
                            <i class='fas fa-phone'></i>
                        </a>";
        }
        if ($element['email']) {
            $mail = $element['email'];
            $contacts .= "<a class='entry-content contact-mail' title='$name eine Mail schreiben' href='mailto:$mail'>
                            <i class='fas fa-envelope'></i>
                        </a>";
        }
        if ($element['website']) {
            $website = $element['website'];
            $contacts .= "<a class='entry-content contact-website' title='Website von $name' href='$website' rel='noopener nofollow' target='_blank'>
                            <i class='fas fa-external-link-square-alt'></i>
                        </a>";
        }
        if ($element['facebook']) {
            $fb = $element['facebook'];
            $contacts .= "<a class='entry-content contact-fb' title='Facebookseite von $name' href='$fb' rel='noopener nofollow' target='_blank'>
                            <i class='fab fa-facebook-square'></i>
                        </a>";
        }
        if ($element['instagram']) {
            $ig = $element['instagram'];
            $contacts .= "<a class='entry-content contact-ig' title='Instagram von $name' href='$ig' rel='noopener nofollow' target='_blank'>
                            <i class='fab fa-instagram-square'></i>
                        </a>";
        }
        if ($element['twitter']) {
            $tw = $element['twitter'];
            $contacts .= "<a class='entry-content contact-tw' title='Twitter von $name' href='$tw' rel='noopener nofollow' target='_blank'>
                            <i class='fab fa-twitter-square'></i>
                        </a>";
        }

        $href = $url . '/' . $element['alias'];
        $clientUuid = $this->requestStack->getCurrentRequest()->cookies->get('clientUuid');
        if ($clientUuid) {
            $selectWishlistEntry = 'SELECT * FROM tl_gutesio_data_wishlist WHERE clientUuid = ? AND dataUuid = ?';
            $wishListEntry = $this->Database->prepare($selectWishlistEntry)->execute($clientUuid, $element['uuid'])->fetchAssoc();
            $urlAdd = 'gutesio/operator/wishlist/add/showcase/' . $element['uuid'];
            $urlRemove = 'gutesio/operator/wishlist/remove/' . $element['uuid'];
            $elementUuid = $element['uuid'];
            $onclickRm = "jQuery.post(\"$urlRemove\");this.innerText = \"Merken\";jQuery(this).attr(\"class\", \"btn btn-primary put-on-wishlist\");";
            $onclickAdd = "jQuery.post(\"$urlAdd\"); this.innerText = \"Gemerkt\";jQuery(this).attr(\"class\", \"btn btn-warning remove-from-wishlist on-wishlist\");";

//            TODO: After jQuery.post we have to trigger the Popup - #ajo

            if (!$wishListEntry) {
                $buttonWishlist = "<a class='btn btn-primary put-on-wishlist' data-uuid='$elementUuid'>Merken <i class=\"far fa-heart\"></i></a>"; //ToDo  <i class=\u0022fas fa-heart\u0022></i>
            } else {
                $buttonWishlist = "<a class='btn btn-warning remove-from-wishlist on-wishlist' title='Von Merkzettel entfernen' data-uuid='$elementUuid'>Gemerkt <i class=\"fas fa-heart\"></i></a>"; //ToDo  <i class=\u0022far fa-heart\u0022></i>
            }
        }

        if ($element['description']) {

            $desc = C4GUtils::truncate($element['description'], 275);
        }
        $settings = GutesioOperatorSettingsModel::findSettings();
        $fields = $reduced ? StringUtil::deserialize($settings->popupFieldsReduced) : StringUtil::deserialize($settings->popupFields);
        $html = "<div class='showcase-tile c4g-tile'>";

        if ((!$fields || in_array('image', $fields)) && $image){
            $html .= "<div class='c4g-tile-header'>
                        <div class='item image'>
                            <a href='$href'>$image</a>
                        </div>
                    </div>";
        }

        $html .= !$reduced ? "<div class='c4g-tile-content'>" : "<a class='c4g-tile-content' href='$href'>";

        if ((!$fields || in_array('name', $fields)) && $name){
            $html .= "<div class='item name'>
                            <h4>$name</h4>
                        </div>";
        }

        if ((!$fields || in_array('types', $fields)) && $strTypes){
            $html .= "<div class='item types'>
                            <span class='entry-label'>Kategorie(n)</span>
                            <span class='entry-content'>$strTypes</span>
                        </div>";
        }

        if ((!$fields || in_array('desc', $fields)) && $desc){
            $html .= "<div class='item description'>
                            <p>$desc</p>
                        </div>";
        }
        if ((!$fields || in_array('tags', $fields)) && $tags){
            $html .= "<div class='tags'>
                            $tags
                        </div>";
        }
        if ((!$fields || in_array('contacts', $fields)) && $contacts){
            $html .= "<div class='item contacts'>
                            $contacts
                        </div>";
        }
        $html .= "</div><div class='c4g-tile-footer'>
                        <div class='item alias'>
                            <span class='entry-content'>";
        if ((!$fields || in_array('wishlist', $fields)) && $buttonWishlist){
            $html .= $buttonWishlist;
        }
        if ((!$fields || in_array('more', $fields))){
            $html .= "<a class='btn btn-primary' href='$href'>Mehr</a>";
        }
        $html .= "    
                            </span>
                        </div>
                    </div>";
        $html .= !$reduced ? "</div>" : "</a>";
        $popup = [
            'async' => false,
            'content' => $html,
            'showPopupOnActive' => '',
            'routing_link' => true,
        ];

        return $popup;
    }
    private function getReducedPopup ($element, $url) {
        $name = $element['name'];
        $strQueryTypes = 'SELECT type.name FROM tl_gutesio_data_type AS type 
                                        INNER JOIN tl_gutesio_data_element_type AS typeElem ON typeElem.typeId = type.uuid
                                        WHERE typeElem.elementId = ?';
        $arrTypes = $this->Database->prepare($strQueryTypes)->execute($element['uuid'])->fetchAllAssoc();
        $strTypes = '';
        foreach ($arrTypes as $type) {
            $strTypes .= $type['name'] . ', ';
        }
        $strTypes = rtrim($strTypes, ', ');
        $href = $url . '/' . $element['alias'];
        $clientUuid = $this->requestStack->getCurrentRequest()->cookies->get('clientUuid');
        if ($clientUuid) {
            $selectWishlistEntry = 'SELECT * FROM tl_gutesio_data_wishlist WHERE clientUuid = ? AND dataUuid = ?';
            $wishListEntry = $this->Database->prepare($selectWishlistEntry)->execute($clientUuid, $element['uuid'])->fetchAssoc();
            $urlAdd = 'gutesio/operator/wishlist/add/showcase/' . $element['uuid'];
            $urlRemove = 'gutesio/operator/wishlist/remove/' . $element['uuid'];
            $elementUuid = $element['uuid'];
            $onclickRm = "jQuery.post(\"$urlRemove\");this.innerText = \"Merken\";jQuery(this).attr(\"class\", \"btn btn-primary put-on-wishlist\");";
            $onclickAdd = "jQuery.post(\"$urlAdd\"); this.innerText = \"Gemerkt\";jQuery(this).attr(\"class\", \"btn btn-warning remove-from-wishlist on-wishlist\");";

            // TODO: After jQuery.post we have to trigger the Popup - #ajo
            if (!$wishListEntry) {
                $buttonWishlist = "<a class='btn btn-primary put-on-wishlist' data-uuid='$elementUuid'>Merken <i class=\"far fa-heart\"></i></a>"; //ToDo  <i class=\u0022fas fa-heart\u0022></i>
            } else {
                $buttonWishlist = "<a class='btn btn-warning remove-from-wishlist on-wishlist' title='Von Merkzettel entfernen' data-uuid='$elementUuid'>Gemerkt <i class=\"fas fa-heart\"></i></a>"; //ToDo  <i class=\u0022far fa-heart\u0022></i>
            }
        }

        if ($element['description']) {

            $desc = C4GUtils::truncate($element['description'], 275);
        }

        $html = "<a href='$href' class='showcase-tile c4g-tile'>
                    <div class='c4g-tile-content'>
                        <div class='item name'>
                            <h4>$name</h4>
                        </div>
                        <div class='item types'>
                            <span class='entry-label'>Kategorie(n)</span>
                            <span class='entry-content'>$strTypes</span>
                        </div>
                        <div class='item description'>
                            <p>$desc</p>
                        </div>                  
                    </div>   
                    <div class='c4g-tile-footer'>
                        <div class='item alias'>
                            <span class='entry-content'>
                                $buttonWishlist
                            </span>
                        </div>
                    </div>
                </a>";
        $popup = [
            'async' => false,
            'content' => $html,
            'showPopupOnActive' => '',
            'routing_link' => true,
        ];

        return $popup;
    }
}
