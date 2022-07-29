<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Controller;


use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\WrapperTileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\Database;
use Contao\ModuleModel;
use Contao\Template;
use gutesio\DataModelBundle\Classes\ShowcaseResultConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MiniWishlistModuleController extends AbstractFrontendModuleController
{
    public const TYPE = 'mini_wishlist_module';

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $list = $this->getList();
        $fields = $this->getListFields();
        $clientUuid = $this->checkCookieForClientUuid($request);
        \System::loadLanguageFile("tl_gutesio_mini_wishlist");
        $data = $this->getListData($clientUuid);
        if (is_array($data) && !($data[0])) {
            $data = [$data];
        }
        foreach ($data as $key => $datum) {
            $typeString = "";
            $types = $datum['types'];
            foreach ($types as $type) {
                $typeString .= $type['label'] . ",";
            }
            $datum['types'] = $typeString;
            $data[$key] = $datum;
        }
        
        $fc = new FrontendConfiguration("mini-wishlist");
        $fc->addTileList($list, $fields, $data);
        $jsonConf = json_encode($fc);
        if ($jsonConf === false) {
            // error encoding
            C4gLogModel::addLogEntry("operator", json_last_error_msg());
            $template->configuration = [];
        } else {
            $template->configuration = $jsonConf;
        }
        
        return $template->getResponse();
    }
    
    private function checkCookieForClientUuid(Request $request)
    {
        $clientUuidCookie = $request->cookies->get('clientUuid');
        if ($clientUuidCookie === null) {
            $clientUuid = C4GUtils::getGUID();
            return $clientUuid;
        } else {
            return $clientUuidCookie;
        }
    }
    
    private function getList()
    {
        $tileList = new TileList("wishlist");
        $tileList->setClassName("memo-popup popup");
        $tileList->setTileClassName("item");
        $tileList->setLayoutType("mini-list");
        $tileList->setHeadline($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['headline']);
        $tileList->setTextAfterUpdate($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['textBeforeUpdate']);
        $tileList->setTextBeforeUpdate($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['textAfterUpdate']);
    
        return $tileList;
    }
    
    private function getListFields()
    {
        $fields = [];
    
        $field = new ImageTileField();
        $field->setName("image");
        $field->setClass("col-4");
        $fields[] = $field;
    
        $field = new WrapperTileField();
        $field->setClass("col-8");
        $field->setWrappedFields(["name"]);
        $fields[] = $field;
    
        $field = new HeadlineTileField();
        $field->setName("name");
        $field->setLevel(3);
        $fields[] = $field;
        
        $field = new WrapperTileField();
        $field->setClass("row mt-2");
        $field->setWrappedFields(["alias", "uuid"]);
        $fields[] = $field;
        
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setHref("/bummeln/alias");
        $field->setHrefField("alias");
        $field->setButtonClass("btn btn-primary");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['moreInfos']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("showcase");
        $fields[] = $field;
    
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setHref("/angebote/alias");
        $field->setHrefField("alias");
        $field->setButtonClass("btn btn-primary");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['buyNow']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("product");
        $fields[] = $field;
    
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setHref("/veranstaltungen/alias");
        $field->setHrefField("alias");
        $field->setButtonClass("btn btn-primary");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['bookNow']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("event");
        $fields[] = $field;
    
        $field = new LinkButtonTileField();
        $field->setName("alias");
        $field->setHref("/jobs/alias");
        $field->setHrefField("alias");
        $field->setButtonClass("btn btn-primary");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['applyNow']);
        $field->setConditionField("internal_type");
        $field->setConditionValue("job");
        $fields[] = $field;
        
        $field = new LinkButtonTileField();
        $field->setName("uuid");
        $field->setHrefField("uuid");
        $field->setHref("/gutesio/operator/wishlist/remove/uuid");
        $field->setLinkText($GLOBALS['TL_LANG']['tl_gutesio_mini_wishlist']['delete']);
        $field->setButtonClass("btn btn-primary");
        $field->setAsyncCall(true);
        $fields[] = $field;
        
        return $fields;
    }
    
    private function getListData($clientUuid)
    {
        $db = Database::getInstance();
        $converter = new ShowcaseResultConverter();
        // limit mini wishlist to three results
        $sql = "SELECT * FROM tl_gutesio_data_wishlist WHERE `clientUuid` = ? LIMIT 3";
        $arrWishlistElements = $db->prepare($sql)->execute($clientUuid)->fetchAllAssoc();
        $arrElements = [];
        foreach ($arrWishlistElements as $element) {
            $table = $element['dataTable'];
            $uuid = $element['dataUuid'];
            $sql = "SELECT * FROM $table WHERE `uuid` = ?";
            $dataEntry = $db->prepare($sql)->execute($uuid)->fetchAssoc();
            unset($dataEntry['logo']);
            unset($dataEntry['imageGallery']);
            if ($table === "tl_gutesio_data_element") {
                $dataEntry['internal_type'] = "showcase";
            } else {
                $typeId = $dataEntry['typeId'];
                $sql = "SELECT * FROM tl_gutesio_data_child_type WHERE `uuid` = ?";
                $arrType = $db->prepare($sql)->execute($typeId)->fetchAssoc();
                $dataEntry['internal_type'] = $arrType['type'];
            }
            
            $arrElements[] = $dataEntry;
        }
        
        return $converter->convertDbResult($arrElements, ['loadTagsComplete' => true]);
    }
}