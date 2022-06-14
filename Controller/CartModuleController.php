<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.
 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */

namespace gutesio\OperatorBundle\Controller;

use con4gis\CoreBundle\Classes\ResourceLoader;
use con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\Template;
use gutesio\OperatorBundle\Classes\Server;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CartModuleController extends AbstractFrontendModuleController
{
    public function __construct(
        ContaoFramework $framework
    ) {
        $framework->initialize();
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        ResourceLoader::loadJavaScriptResource(
            'bundles/gutesiooperator/dist/js/cart.js',
            ResourceLoader::HEAD
        );

        $con4gisSettings = C4gSettingsModel::findSettings();

        $template->getCartUrl = Server::URL.'/gutesio/main/cart/items';
        $template->cart_payment_url = rtrim($con4gisSettings->con4gisIoUrl, '/') . '/cart.php';
        $template->cart_no_items_text = nl2br($model->cart_no_items_text);

        return $template->getResponse();
    }
}