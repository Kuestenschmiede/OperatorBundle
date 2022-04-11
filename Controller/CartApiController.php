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
use con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\PageModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildTypeModel;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataElementModel;
use gutesio\OperatorBundle\Classes\Curl\CurlGetRequest;
use gutesio\OperatorBundle\Classes\Curl\CurlPostRequest;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Services\OfferLoaderService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartApiController extends AbstractController
{
    private $proxyUrl;
    private $offerService;

    public const GET_CART_URL = 'getCart.php';
    public const ADD_CART_URL = 'addToCart.php';
    public const REMOVE_ALL_CART_URL = 'removeAllFromCart.php';
    public const CONFIG_CART_URL = 'configCart.php';

    public function __construct(
        ContaoFramework $framework,
        OfferLoaderService $offerService
    ) {
        $framework->initialize();
        $settings = C4gSettingsModel::findSettings();
        $this->proxyUrl = $settings->con4gisIoUrl;
        $this->offerService = $offerService;
    }

    /**
     * @Route(
     *     "/gutesio/operator/cart/items",
     *     name="gutesio_operator_cart_items",
     *     methods={"GET"}
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function getCartItems(Request $request) : JsonResponse {
        $response = new JsonResponse();
        $member = FrontendUser::getInstance();
        if ($member->id < 1 || (string) $member->cartId === '') {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            return $response;
        }
        $curlRequest = new CurlGetRequest();
        $curlRequest->setUrl($this->proxyUrl . '/' . self::GET_CART_URL);
        $curlRequest->setParameters(['cartId' => $member->cartId]);
        $curlResponse = $curlRequest->send();
        $response->setStatusCode((int) $curlResponse->getStatusCode());
        $data = $curlResponse->getData();
        $data = json_decode($data, true);
        $data['configCartUrl'] = '/gutesio/operator/cart/config';
        $data['removeCartUrl'] = '/gutesio/operator/cart/remove';
        $data['removeAllCartUrl'] = '/gutesio/operator/cart/removeAll';
        $data['hiddenClass'] = 'hidden';

        $database =  Database::getInstance();
        foreach ($data['vendors'] as $key => $vendor) {
            $statement = $database->prepare(
                "SELECT logo FROM tl_gutesio_data_element WHERE uuid = ?"
            );
            $result = $statement->execute($vendor['uuid'])->fetchAssoc();
            $imageUuid = $result['logo'];
            $imageModel = FilesModel::findByUuid($imageUuid);
            if ($imageModel !== null) {
                $data['vendors'][$key]['image'] = [
                    'src' => $imageModel->path,
                    'alt' => ''
                ];
            }
            foreach ($data['vendors'][$key]['articles'] as $k => $article) {
                $statement = $database->prepare(
                    "SELECT image FROM tl_gutesio_data_child WHERE uuid = ?"
                );
                $result = $statement->execute($article['childId'])->fetchAssoc();
                $imageModel = FilesModel::findByUuid($result['image']);
                if ($imageModel !== null) {
                    $data['vendors'][$key]['articles'][$k]['image'] = [
                        'src' => $imageModel->path,
                        'alt' => ''
                    ];
                }
            }
        }

        $response->setData($data);

        return $response;
    }

    /**
     * @Route(
     *     "/gutesio/operator/cart/add",
     *     name="gutesio_operator_cart_add",
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function addCartItem(Request $request) : JsonResponse {
        $response = new JsonResponse();
        $member = FrontendUser::getInstance();
        if ($member->id < 1 || (string) $member->cartId === '') {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            return $response;
        }
        $settings = GutesioOperatorSettingsModel::findSettings();
        $elementId = $request->request->get('elementId', '');
        $elementModel = GutesioDataElementModel::findByUuid($elementId);
        $elementAlias = $elementModel->alias;
        $showcaseLink = $this->findUrlFromPage($settings->showcaseDetailPage, $elementAlias);
        $childId = $request->request->get('childId', '');
        $childModel = GutesioDataChildModel::findByUuid($childId);
        $childTypeModel = GutesioDataChildTypeModel::findBy('uuid', $childModel->typeId, ['return' => 'Model']);

        //php >=8.0 stuff
        $childLink = match ($childTypeModel->type) {
            'product' => $settings->productDetailPage,
            'voucher' => $settings->voucherDetailPage,
            default => '',
        };
        $childAlias = $request->request->get('childId', '');
        $childAlias = strtolower(str_replace(['{', '}'], '', $childAlias));
        $childLink = $this->findUrlFromPage($childLink, $childAlias);

        $curlRequest = new CurlPostRequest();
        $curlRequest->setUrl($this->proxyUrl . '/' . self::ADD_CART_URL);
        $curlRequest->setPostData(
            array_merge(
                $request->request->all(),
                [
                    'cartId' => $member->cartId,
                    'showcaseLink' => $showcaseLink,
                    'childLink' => $childLink
                ]
            )
        );
        $curlResponse = $curlRequest->send();
        $response->setStatusCode((int) $curlResponse->getStatusCode());
        return $response;
    }
    
    /**
     * @Route(
     *     "/gutesio/operator/cart/add/{uuid}",
     *     name="gutesio_operator_cart_add_uuid",
     *     methods={"POST"}
     * )
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function addCartItemWithUuid(Request $request, string $uuid) : JsonResponse {
        $response = new JsonResponse();
        $member = FrontendUser::getInstance();
        if ($member->id < 1 || (string) $member->cartId === '') {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            return $response;
        }

        $settings = GutesioOperatorSettingsModel::findSettings();
        $childModel = GutesioDataChildModel::findByUuid($uuid);
        $childTypeModel = GutesioDataChildTypeModel::findBy('uuid', $childModel->typeId, ['return' => 'Model']);
        $childLink = match ($childTypeModel->type) {
            'product' => $settings->productDetailPage,
            'voucher' => $settings->voucherDetailPage,
            default => '',
        };
        $childAlias = strtolower(str_replace(['{', '}'], '', $uuid));
        $childLink = $this->findUrlFromPage((int) $childLink, $childAlias);

        $elementModel = GutesioDataElementModel::findByChildModel($childModel);
        $elementAlias = $elementModel->alias;
        $showcaseLink = $this->findUrlFromPage((int) $settings->showcaseDetailPage, $elementAlias);

        $curlRequest = new CurlPostRequest();
        $curlRequest->setUrl($this->proxyUrl . '/' . self::ADD_CART_URL);
        $db = Database::getInstance();
        $arrConnections = $db->prepare("SELECT * FROM tl_gutesio_data_child_connection WHERE `childId` = ?")
            ->execute($uuid)->fetchAllAssoc();
        $data = [];
        if ($arrConnections && count($arrConnections) === 1) {
            $elementId = $arrConnections[0]['elementId'];
            $postData = [
                'cartId' => $member->cartId,
                'childId' => $uuid,
                'elementId' => $elementId,
                'elementLink' => $showcaseLink,
                'childLink' => $childLink
            ];
            $curlRequest->setPostData($postData);
            $curlResponse = $curlRequest->send();
            $response->setStatusCode((int) $curlResponse->getStatusCode());

            if ((int) $curlResponse->getStatusCode() === 200) {
                $this->offerService->setRequest($request);
                $row = $this->offerService->getSingleDataset(
                    strtolower(str_replace(['{', '}'], '', $uuid)),
                    true
                );
                $data = array_merge($row, [
                    'updatedData' => [
                        'in_cart' => '1',
                        'not_in_cart' => ''
                    ],
                    'updateType' => 'single'
                ]);
            }
            $response->setData($data);
            return $response;
        } else {
            return new JsonResponse([], 400);
        }
    }

    /**
     * @Route(
     *     "/gutesio/operator/cart/config",
     *     name="gutesio_operator_cart_config",
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function configCartItem(Request $request) : JsonResponse {
        $response = new JsonResponse();
        $member = FrontendUser::getInstance();
        if ($member->id < 1 || (string) $member->cartId === '') {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            return $response;
        }
        $curlRequest = new CurlPostRequest();
        $curlRequest->setUrl($this->proxyUrl . '/' . self::CONFIG_CART_URL);
        $curlRequest->setPostData(array_merge($request->request->all(), ['cartId' => $member->cartId]));
        $curlResponse = $curlRequest->send();
        $response->setStatusCode((int) $curlResponse->getStatusCode());
        return $response;
    }

    /**
     * @Route(
     *     "/gutesio/operator/cart/removeAll",
     *     name="gutesio_operator_cart_remove_all",
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function removeAllCartItems(Request $request) : Jsonresponse {
        $response = new JsonResponse();
        $member = FrontendUser::getInstance();
        if ($member->id < 1 || (string) $member->cartId === '') {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            return $response;
        }
        $curlRequest = new CurlPostRequest();
        $curlRequest->setUrl($this->proxyUrl . '/' . self::REMOVE_ALL_CART_URL);
        $curlRequest->setPostData(['cartId' => $member->cartId]);
        $curlResponse = $curlRequest->send();
        $response->setStatusCode((int) $curlResponse->getStatusCode());
        return $response;
    }

    private function findUrlFromPage(int $pageId, string $alias) : string
    {
        $pageModel = PageModel::findByPk($pageId);
        $url = $pageModel->getAbsoluteUrl();
        if (C4GUtils::stringContains($url, '.html')) {
            return str_replace('.html', "/$alias.html", $url);
        } else {
            return $url.'/'.$alias;
        }
    }
}