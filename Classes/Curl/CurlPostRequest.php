<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes\Curl;

class CurlPostRequest
{
    private $url = '';
    private $postData = [];
    private $headers = [];

    /**
     * @var CurlResponse
     */
    private $response;

    public function send()
    {
        $this->response = new CurlResponse();
        $curl = curl_init($this->url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER ,true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $this->postData);
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, [$this, 'setResponseHeader']);
        if (!empty($this->headers)) {
            $headers = [];
            foreach($this->headers as $k => $v) {
                $headers[] = "$k: $v";
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $data = curl_exec($curl);
        $this->response->setStatusCode(curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        curl_close($curl);
        $this->response->setData($data);

        return $this->response;
    }

    private function setResponseHeader($curl, $header)
    {
        $this->response->setHeader($header);
        return strlen($header);
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @param array $postData
     */
    public function setPostData($postData)
    {
        $this->postData = $postData;
    }
    /**
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }
}