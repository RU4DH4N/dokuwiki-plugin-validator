<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

class action_plugin_validator extends ActionPlugin
{
    private const CAPTCHA_PROVIDERS = [
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

    public function register(EventHandler $controller)
    {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'injectScript');
        $controller->register_hook('FORM_REGISTER_OUTPUT', 'BEFORE', $this, 'handleFormOutput');
        $controller->register_hook('FORM_LOGIN_OUTPUT', 'BEFORE', $this, 'handleFormOutput');
        $controller->register_hook('AUTH_USER_CHANGE', 'BEFORE', $this, 'handleRegister');
        $controller->register_hook('AUTH_LOGIN_CHECK', 'BEFORE', $this, 'handleLogin');
    }

    private function getMode(): string
    {
        return $this->getConf('mode') ?: self::DEFAULT;
    }

    private function getHTML() {
        $mode = $this->getMode();
        $sitekey = $this->getConf(self::CAPTCHA_PROVIDERS[$mode]['sitekey_name']);
        $class = self::CAPTCHA_PROVIDERS[$mode]['class_name'];
        return '<div style="margin-top: 10px; margin-bottom: 10px;" class="' . htmlspecialchars($class) . '" data-sitekey="' . htmlspecialchars($sitekey) . '"></div>';
    }

    public function injectScript(Event $event, $param)
    {
        global $ACT;

        if ($ACT === 'register' || $ACT === 'login') {
            $src = self::CAPTCHA_PROVIDERS[$this->getMode()]['challenge_script'];
            $event->data['script'][] = [
                'type'    => 'text/javascript',
                'src'     => $src,
                'async'   => 'async',
                'defer'   => 'defer',
                '_data'   => ''
            ];
        }
    }

    public function handleFormOutput(Event $event, $param)
    {
        if(!$html = $this->getHTML()) return;

        $form = $event->data;

        $pos = $form->findPositionByAttribute('type', 'submit');
        $form->addHTML($html, $pos);
    }

    private function generateResponse(Event $event): ?array {
        $mode = $this->getMode();
        $secretkey = $this->getConf(self::CAPTCHA_PROVIDERS[$mode]['secretkey_name']);

        global $INPUT;

        $response = $INPUT->post->str(self::CAPTCHA_PROVIDERS[$mode]['response_name']);
        if (empty($response)) return null;
      
        $data = [
            'secret' => $secretkey,
            'response' => $response,
        ];

        $ip = $INPUT->server->str('REMOTE_ADDR');
        if (!empty($ip)) $data['remoteip'] = $ip;

        $function = self::CAPTCHA_PROVIDERS[$mode]['function_name'];
        $helper = plugin_load('helper', 'validator');
        $curl = $helper->$function(self::CAPTCHA_PROVIDERS[$mode]['verify_endpoint'], $data);

        $responseBody = curl_exec($curl);

        if (curl_errno($curl) || curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200) {
            curl_close($curl);
            return null;
        }
    
        curl_close($curl);

        $outcome = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) return null;

        return $outcome;
    }

    private function checkToken(Event $event)
    {
        $mode = $this->getMode();

        $sitekey = $this->getConf(self::CAPTCHA_PROVIDERS[$mode]['sitekey_name']);
        $secretkey = $this->getConf(self::CAPTCHA_PROVIDERS[$mode]['secretkey_name']);
        if (empty($sitekey) || empty($secretkey)) return;

        $response = $this->generateResponse($event);


        if (!is_null($response) && isset($response['success']) && $response['success'] === true) {
            return;
        }

        // add msg here

        global $INPUT;
        global $ACT;
        
        switch($ACT) {
            case 'register':
                $INPUT->post->set('save', false);
                break;
            case 'login':
                $event->result = false;
                $event->preventDefault();
                $event->stopPropagation();
                break;
        }
        return;
    }

    public function handleRegister(Event $event, $param)
    {
        if ($event->data['type'] !== 'create') {
            return;
        }

        $this->checkToken($event);
    }

    public function handleLogin(Event $event, $param)
    {
        global $INPUT;
        if (!$INPUT->bool('u')) return;

        $this->checkToken($event);
    }
}