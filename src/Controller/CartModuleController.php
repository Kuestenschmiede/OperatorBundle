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
use gutesio\OperatorBundle\Classes\Services\ServerService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CartModuleController extends AbstractFrontendModuleController
{
    private ServerService $serverService;

    public function __construct(
        ContaoFramework $framework,
        ServerService $serverService
    ) {
        $framework->initialize();
        $this->serverService = $serverService;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        ResourceLoader::loadJavaScriptResource(
            'bundles/gutesiooperator/dist/js/cart.js',
            ResourceLoader::HEAD
        );

        $con4gisSettings = C4gSettingsModel::findSettings();

        // Operator-Frontend: Cart-API liegt auf der Main-Instance → absolute URL verwenden
        // Basis-URL ermitteln: Protokoll nur ergänzen, wenn keines vorhanden ist
        $baseUrl = $this->serverService->getMainServerURL();
        if (!preg_match('#^https?://#i', $baseUrl)) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }
        $template->getCartUrl = rtrim($baseUrl, '/') . '/gutesio/main/cart/items';
        $template->cart_payment_url = rtrim($con4gisSettings->con4gisIoUrl, '/') . '/cart.php';
        $template->cart_no_items_text = nl2br($model->cart_no_items_text);

        return $template->getResponse();
    }
}