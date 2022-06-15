<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Curl;

class CurlGetRequest
{
    private $url = '';
    private $headers = [];
    private $parameters = [];

    /**
     * @var CurlResponse
     */
    private $response;

    public function send()
    {
        $this->response = new CurlResponse();
        $url = $this->url;
        if (!empty($this->parameters)) {
            $parameters = [];
            foreach ($this->parameters as $k => $v) {
                $parameters[] = "$k=$v";
            }
            $url .= '?'.implode('&', $parameters);
        }
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER ,true);
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
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }
}