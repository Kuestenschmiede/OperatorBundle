<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Curl;

class CurlPostRequest
{
    private string $url = '';
    private string|array $postData = [];
    private array $headers = [];
    private CurlResponse $response;
    private string $user = '';
    private string $password = '';

    public function send(): CurlResponse
    {
        $this->response = new CurlResponse();
        $curl = curl_init($this->url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER ,true);
        curl_setopt($curl, CURLOPT_ENCODING, ""); // Handle all encodings
        curl_setopt($curl, CURLOPT_TIMEOUT, 90); // Timeout after 90 seconds
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout after 10 seconds
        if ($this->postData) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->postData);
        }
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, [$this, 'setResponseHeader']);
        curl_setopt($curl, CURLOPT_USERPWD, $this->user.':'.$this->password);
        if (!empty($this->headers)) {
            $headers = [];
            foreach($this->headers as $k => $v) {
                $headers[] = "$k: $v";
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $data = curl_exec($curl);
        if ($data === false) {
            $this->response->setStatusCode(0);
            $this->response->setData('Curl error: ' . curl_error($curl));
        } else {
            $this->response->setStatusCode(curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
            $this->response->setData($data);
        }
        curl_close($curl);

        return $this->response;
    }

    private function setResponseHeader($curl, $header): int
    {
        $this->response->setHeader($header);
        return strlen($header);
    }

    public function setUrl(string $url)
    {
        $this->url = $url;
    }

    public function setPostData(string|array $postData)
    {
        $this->postData = $postData;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    public function setUser(string $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }
}