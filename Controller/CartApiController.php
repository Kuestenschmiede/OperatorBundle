<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Controller;

use con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FilesModel;
use Contao\FrontendUser;
use gutesio\OperatorBundle\Classes\Curl\CurlGetRequest;
use gutesio\OperatorBundle\Classes\Curl\CurlPostRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartApiController extends AbstractController
{
    private $proxyUrl;

    public const GET_CART_URL = 'getCart.php';
    public const ADD_CART_URL = 'addToCart.php';
    public const REMOVE_ALL_CART_URL = 'removeAllFromCart.php';
    public const CONFIG_CART_URL = 'configCart.php';

    public function __construct(
        ContaoFramework $framework
    ) {
        $framework->initialize();
        $settings = C4gSettingsModel::findSettings();
        $this->proxyUrl = $settings->con4gisIoUrl;
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
        $curlRequest = new CurlPostRequest();
        $curlRequest->setUrl($this->proxyUrl . '/' . self::ADD_CART_URL);
        $curlRequest->setPostData(array_merge($request->request->all(), ['cartId' => $member->cartId]));
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
     * @return JsonResponse
     */
    public function addCartItemWithUuid(Request $request, string $uuid) : JsonResponse {
        $response = new JsonResponse();
        $member = FrontendUser::getInstance();
        if ($member->id < 1 || (string) $member->cartId === '') {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            return $response;
        }
        $curlRequest = new CurlPostRequest();
        $curlRequest->setUrl($this->proxyUrl . '/' . self::ADD_CART_URL);
        $db = Database::getInstance();
        $arrConnections = $db->prepare("SELECT * FROM tl_gutesio_data_child_connection WHERE `childId` = ?")
            ->execute($uuid)->fetchAllAssoc();
        if ($arrConnections && count($arrConnections) === 1) {
            $elementId = $arrConnections[0]['elementId'];
            $postData = [
                'cartId' => $member->cartId,
                'childId' => $uuid,
                'elementId' => $elementId
            ];
            $curlRequest->setPostData($postData);
            $curlResponse = $curlRequest->send();
            $response->setStatusCode((int) $curlResponse->getStatusCode());
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
}