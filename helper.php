<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\Logger;

class helper_plugin_validator extends Plugin
{
    public function getMethods()
    {
        $result = [];
        $result[] = [
            'name' => 'getTurnstile',
            'desc' => 'returns the curl handle for turnstile verification',
            'params' => [
                'url'   => 'string',
                'data'  => 'array'
            ],
            'return' => ['curl' => 'CurlHandle'],
        ];
        $result[] = [
            'name' => 'getCaptcha',
            'desc' => 'returns the curl handle for captcha verification',
            'params' => [
                'url'   => 'string',
                'data'  => 'array'
            ],
            'return' => ['curl' => 'CurlHandle'],
        ];

        return $result;
    }

    public function getTurnstile($url, $data): CurlHandle
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        return $curl;
    }

    public function getCaptcha($url, $data): CurlHandle
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        return $curl;
    }
}