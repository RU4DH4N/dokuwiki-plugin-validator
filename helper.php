<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\Logger;

class helper_plugin_validator extends Plugin
{
    private const CAPTCHA_PROVIDERS = [
        /**
         * challenge_script - the challenge script
         * verify_endpoint  - verifys challenges
         * sitekey_name     - the name of the sitekey in conf
         * class_name       - the name the div's class needs to generate the tick box
         * response_name    - the name of the response in html
         * function_name    - the name of the function that checks if response valid
         */
        'cloudflare' => [
            'challenge_script'  => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'verify_endpoint'   => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'sitekey_name'      => 'cf-sitekey',
            'secretkey_name'    => 'cf-secretkey',
            'class_name'        => 'cf-turnstile',
            'response_name'     => 'cf-turnstile-response',
            'function_name'     => 'getTurnstile',
        ],
        'google' => [
            'challenge_script'  => 'https://www.google.com/recaptcha/api.js',
            'verify_endpoint'   => 'https://www.google.com/recaptcha/api/siteverify',
            'sitekey_name'      => 'g-sitekey',
            'secretkey_name'    => 'g-secretkey',
            'class_name'        => 'g-recaptcha',
            'response_name'     => 'g-recaptcha-response',
            'function_name'     => 'getCaptcha',
        ],
    ];

    private const DEFAULT = 'cloudflare';

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
            'return' => ['curl' => 'CurlHandle|resource|false'],
        ];
        $result[] = [
            'name' => 'getCaptcha',
            'desc' => 'returns the curl handle for captcha verification',
            'params' => [
                'url'   => 'string',
                'data'  => 'array'
            ],
            'return' => ['curl' => 'CurlHandle|resource|false'],
        ];

        $result[] = [
            'name' => 'getProvider',
            'desc' => 'Returns the configuration array for the currently selected CAPTCHA provider.',
            'params' => [],
            'return' => ['providerConfig' => 'array'],
        ];
        $result[] = [
            'name' => 'getHTML',
            'desc' => 'Returns the HTML markup for displaying the CAPTCHA challenge.',
            'params' => [],
            'return' => ['html' => 'string'],
        ];
        $result[] = [
            'name' => 'check',
            'desc' => 'Performs the CAPTCHA verification check.',
            'params' => [
                'msg' => 'bool (optional, default: true) - Whether to log debug messages.'
            ],
            'return' => ['isValid' => 'bool'],
        ];

        return $result;
    }

    // I need to implement this properly
    public function isEnabled():bool {
        $provider = $this->getProvider();
        $sitekey = $this->getConf($provider['sitekey_name']);
        $secretkey = $this->getConf($provider['secretkey_name']);
        return ($sitekey !== false && $sitekey !== '' && $secretkey !== false && $secretkey !== '');
    }

    public function getProvider() {
        $provider = $this->getConf('provider') ?: self::DEFAULT;
        return self::CAPTCHA_PROVIDERS[$provider];
    }

    public function getHTML(): string
    {
        $provider = $this->getProvider();
        $sitekey = $this->getConf($provider['sitekey_name']);

        return '<div style="margin-top: 10px; margin-bottom: 10px;" class="' . 
                    htmlspecialchars($provider['class_name']) . 
                    '" data-sitekey="' .
                    htmlspecialchars($sitekey) .
                '"></div>';
    }

    private function processResponse($curl): bool
    {
        if ($curl === false) return false;

        $responseBody = curl_exec($curl);

        if (curl_errno($curl) || curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200) {
            curl_close($curl);
            return false;
        }

        curl_close($curl);

        $outcome = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) return false;

        return $outcome !== null && isset($outcome['success']) && $outcome['success'] === true;
    }

    public function check($msg = true): bool
    {
        $provider = $this->getProvider();
        
        $sitekey = $this->getConf($provider['sitekey_name']);
        $secretkey = $this->getConf($provider['secretkey_name']);

        if (empty($sitekey) || empty($secretkey)) {
            // TODO: use lang
            Logger::debug('validator not configured correctly, please set sitekey / secretkey');
            return true; // this has to return true even if no checks are done. 
        }

        global $INPUT;

        $response = $INPUT->post->str($provider['response_name']);
        if (empty($response)) return false;

        $data = [
            'secret' => $secretkey,
            'response' => $response,
        ];

        $ip = $INPUT->server->str('REMOTE_ADDR');
        // probably unnecessary
        if (!empty($ip)) $data['remoteip'] = $ip;

        $function = $provider['function_name'];
        $verify_endpoint = $provider['verify_endpoint'];

        if (empty($function) || empty($verify_endpoint)) return false;

        $curl = $this->$function($verify_endpoint, $data);
        return $this->processResponse($curl);
    }

    public function getTurnstile($url, $data)
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

    public function getCaptcha($url, $data)
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
