<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.
 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */

namespace gutesio\OperatorBundle\Controller;


use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\ResourceLoader;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel;
use con4gis\FrameworkBundle\Classes\FormButtons\FilterButton;
use con4gis\FrameworkBundle\Classes\FormFields\DateRangeField;
use con4gis\FrameworkBundle\Classes\FormFields\HiddenFormField;
use con4gis\FrameworkBundle\Classes\FormFields\MultiCheckboxWithImageLabelFormField;
use con4gis\FrameworkBundle\Classes\FormFields\NumberRangeFormField;
use con4gis\FrameworkBundle\Classes\FormFields\RadioGroupFormField;
use con4gis\FrameworkBundle\Classes\FormFields\RequestTokenFormField;
use con4gis\FrameworkBundle\Classes\FormFields\SelectFormField;
use con4gis\FrameworkBundle\Classes\FormFields\TextAreaFormField;
use con4gis\FrameworkBundle\Classes\FormFields\TextFormField;
use con4gis\FrameworkBundle\Classes\Forms\Form;
use con4gis\FrameworkBundle\Classes\Forms\ToggleableForm;
use con4gis\FrameworkBundle\Classes\FrontendConfiguration;
use con4gis\FrameworkBundle\Classes\SearchConfiguration;
use con4gis\FrameworkBundle\Classes\TileFields\HeadlineTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ImageTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\LinkTileField;
use con4gis\FrameworkBundle\Classes\TileFields\ModalButtonTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TagTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TextTileField;
use con4gis\FrameworkBundle\Classes\TileFields\TileField;
use con4gis\FrameworkBundle\Classes\TileFields\WrapperTileField;
use con4gis\FrameworkBundle\Classes\TileLists\TileList;
use con4gis\FrameworkBundle\Classes\Utility\RegularExpression;
use con4gis\FrameworkBundle\Traits\AutoItemTrait;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildVoucherModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartModuleController extends AbstractFrontendModuleController
{
    const COOKIE_CART = "cart";

    public function __construct(ContaoFramework $framework)
    {
        $framework->initialize();
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
//        ResourceLoader::loadJavaScriptResource(
//            'bundles/gutesiooperator/src/js/react.production.min.js',
//            ResourceLoader::HEAD
//        );
//        ResourceLoader::loadJavaScriptResource(
//            'bundles/gutesiooperator/src/js/react-dom.production.min.js',
//            ResourceLoader::HEAD
//        );
        ResourceLoader::loadJavaScriptResource(
            'bundles/gutesiooperator/src/js/react.development.js',
            ResourceLoader::HEAD
        );
        ResourceLoader::loadJavaScriptResource(
            'bundles/gutesiooperator/src/js/react-dom.development.js',
            ResourceLoader::HEAD
        );
        ResourceLoader::loadJavaScriptResource(
            'bundles/gutesiooperator/src/js/cart.js',
            ResourceLoader::HEAD
        );

        $template->toggleButtonText = 'Warenkorb';
        $template->cartClass = 'cart';
        $template->getCartUrl = '/gutesio/operator/cart/items';
        $template->addCartUrl = '/gutesio/operator/cart/add';
        $template->removeCartUrl = '/gutesio/operator/cart/remove';
        $template->configCartUrl = '/gutesio/operator/cart/config';
        $template->toggleClass = 'hidden';
        return $template->getResponse();
    }
}