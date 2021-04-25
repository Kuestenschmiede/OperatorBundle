<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.
 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */

namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\MapsBundle\Classes\Events\LoadInfoWindowEvent;
use Contao\Controller;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
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
    )
    {
        $popup = $event->getPopup();
        $requestString = $event->getPopupString();
        $reqParams = explode('::', $requestString);
        if ($reqParams[0] === 'showcase' && $reqParams[1]) {
            $objElement = $this->Database->prepare('SELECT * FROM tl_gutesio_data_element WHERE uuid = ?')->execute($reqParams[1])->fetchAssoc();
            $objSettingsModel = GutesioOperatorSettingsModel::findSettings();
            $url = Controller::replaceInsertTags('{{link_url::' . $objSettingsModel->showcaseDetailPage . '}}');
            $strQueryTags = 'SELECT tag.uuid, tag.image, tag.name FROM tl_gutesio_data_tag AS tag
                                        INNER JOIN tl_gutesio_data_tag_element AS elementTag ON elementTag.tagId = tag.uuid
                                        WHERE tag.published = 1 AND elementTag.elementId = ? ORDER BY tag.name ASC';
            $arrTags = $this->Database->prepare($strQueryTags)->execute($objElement['uuid'])->fetchAllAssoc();
            $popup = array_merge($this->getPopup($objElement, $url, $arrTags));
        }
        $event->setPopup($popup);
    }

    private function getPopup($element, $url, $arrTags)
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
        $imageUuid = StringUtil::binToUuid($element['imagePopup']);
        $file = FilesModel::findByUuid($imageUuid) ? FilesModel::findByUuid($imageUuid) : FilesModel::findByUuid(StringUtil::binToUuid($element['image']));

        $strImage = $file->path;
        if ($strImage) {
            $alt = $file->meta && unserialize($file->meta)['de'] ? unserialize($file->meta)['de']['alt'] : $name;
            $image = "<img class='entry-content' src='$strImage' alt='$alt' title='$name'>";
        }
        $tags = '';
        foreach ($arrTags as $tag) {
            $imageTagUuid = StringUtil::binToUuid($tag['image']);
            $fileTag = FilesModel::findByUuid($imageTagUuid);
            $tags .= "<div class='item " . $tag['name'] . "'>
                        <img class='entry-content " . $tag['name'] . "' src='" . $fileTag->path . "' alt='" . $tag['name'] . "' title='" . $tag['name'] . "'>
                        </div>";
        }
        $href = $url . '/' . $element['alias'];

        $clientUuid = $this->requestStack->getCurrentRequest()->cookies->get('clientUuid');
        if ($clientUuid) {
            $selectWishlistEntry = 'SELECT * FROM tl_gutesio_data_wishlist WHERE clientUuid = ? AND dataUuid = ?';
            $wishListEntry = $this->Database->prepare($selectWishlistEntry)->execute($clientUuid, $element['uuid'])->fetchAssoc();
            $urlAdd = 'gutesio/operator/wishlist/add/showcase/' . $element['uuid'];
            $urlRemove = 'gutesio/operator/wishlist/remove/' . $element['uuid'];

            $onclickRm = "jQuery.post(\"$urlRemove\");this.innerText = \"Merken\";jQuery(this).attr(\"class\", \"btn btn-primary put-on-wishlist\");";
            $onclickAdd = "jQuery.post(\"$urlAdd\"); this.innerText = \"Gemerkt\";jQuery(this).attr(\"class\", \"btn btn-warning remove-from-wishlist on-wishlist\");";

//            TODO: After jQuery.post we have to trigger the Popup - #ajo

            if (!$wishListEntry) {
                $buttonWishlist = "<a class='btn btn-primary put-on-wishlist' onclick='$onclickAdd'>Merken <i class=\"far fa-heart\"></i></a>"; //ToDo  <i class=\u0022fas fa-heart\u0022></i>
            } else {
                $buttonWishlist = "<a class='btn btn-warning remove-from-wishlist on-wishlist' title='Von Merkzettel entfernen' onclick='$onclickRm'>Gemerkt <i class=\"fas fa-heart\"></i></a>"; //ToDo  <i class=\u0022far fa-heart\u0022></i>
            }
        }

        $html = "<div class='showcase-tile c4g-tile'>
                     <div class='c4g-tile-header'>
                        <div class='item image'>
                            $image
                        </div>
                    </div>
                    <div class='c4g-tile-content'>
                        <div class='item name'>
                            <h4>$name</h4>
                        </div>
                        <div class='item types'>
                            <span class='entry-label'>Kategorie(n)</span>
                            <span class='entry-content'>$strTypes</span>
                        </div>
                        <div class='tags'>
                            $tags
                        </div>
                    </div>   
                    <div class='c4g-tile-footer'>
                        <div class='item alias'>
                            <span class='entry-content'>
                                $buttonWishlist
                                <a class='btn btn-primary' href='$href'>Mehr</a>
                            </span>
                        </div>
                    </div>
                </div>";
        $popup = [
            'async' => false,
            'content' => $html,
            'showPopupOnActive' => '',
            'routing_link' => true,
        ];

        return $popup;
    }
}
