<?php

namespace App\Services;

class BaseService
{
    /**
     * Base service class for common functionality
     */
    public function __construct()
    {
    }

    public function doRequest($url, $params = [], $method = 'get', $header = [])
    {
        $client = new Client(['headers' => $header]);
        try {
            if (strtolower($method) == 'get') {
                return $client->request($method, addParamToUrl($url, $params));
            }
            return $client->request($method, $url, [
                'body' => $params
            ]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                logError($e->getResponse()->getBody());
            }
        } catch (\Exception $e) {
            logError($e->getMessage());
        }
        return false;
    }

    public function getDataResponse($response)
    {
        if ($response) {
            $data = json_decode($response->getBody()->getContents());
            return $data->data;
        }
        return null;
    }

    public function getDataResponseGhtk($response)
    {
        if ($response) {
            $data = json_decode($response->getBody()->getContents(), true);
            return $data;
        }
        return null;
    }

    public function getDataResponseVtp($response)
    {
        if ($response) {
            $data = json_decode($response->getBody()->getContents(), true);
            return $data;
        }
        return null;
    }
}
