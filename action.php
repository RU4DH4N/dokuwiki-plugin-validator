<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

class action_plugin_turnstile extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'injectScript');

        $controller->register_hook('FORM_REGISTER_OUTPUT', 'BEFORE', $this, 'handleFormOutput', []);

        $controller->register_hook('FORM_LOGIN_OUTPUT', 'BEFORE', $this, 'handleFormOutput', []);

        $controller->register_hook('AUTH_USER_CHANGE', 'BEFORE', $this, 'handleRegister', []);

        $controller->register_hook('AUTH_LOGIN_CHECK', 'BEFORE', $this, 'handleLogin', []);
    }

    private function getHTML() {
        $siteKey = $this->getConf('sitekey');

        $html = '<div style="margin-top: 10px; margin-bottom: 10px;">';
        $html .= '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars($siteKey) . '" data-callback="turnstileCallback"></div>';
        $html .= '</div>';
        return $html;
    }

    public function injectScript(Event $event, $param)
    {
        global $ACT;

        if ($ACT === 'register' || $ACT === 'login') {
            $event->data['script'][] = [
                'type'    => 'text/javascript',
                'src'     => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
                'async'   => 'async',
                'defer'   => 'defer',
                '_data'   => ''
            ];
        }
    }

    public function handleFormOutput(Event $event, $param)
    {
        $form = $event->data;

        $html = $this->getHTML();
        if (empty($html)) return;

        $pos = $form->findPositionByAttribute('type', 'submit');
        if(!$pos) return;

        $form->addHTML($html, $pos);
    }

    private function generateResponse(Event $event): ?array {
        $private = $this->getConf('secretkey');
        if (empty($private)) return null;

        global $INPUT;

        $ts_response = $INPUT->post->str('cf-turnstile-response');
        if (empty($ts_response)) return null;

        $ip = $INPUT->server->str('REMOTE_ADDR');

        $data = [
            'secret' => $private,
            'response' => $ts_response,
            'remoteip' => $ip
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);

        if ($result === false) return null;

        $outcome = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) return null;

        return $outcome;
    }

    private function validateResponse($response): bool {
        return isset($response['success']) && $response['success'] === true;
    }

    public function handleRegister(Event $event, $param)
    {
        if ($event->data['type'] !== 'create') {
            return;
        }

        $public = $this->getConf('sitekey');
        $private = $this->getConf('secretkey');
        if (empty($public) || empty($private)) return;

        global $INPUT;

        $response = $this->generateResponse($event);
        if(is_null($response) || !$this->validateResponse($response)) {
            $INPUT->post->set('save', false);
        }
    }

    public function handleLogin(Event $event, $param)
    {
        $public = $this->getConf('sitekey');
        $private = $this->getConf('secretkey');
        if (empty($public) || empty($private)) return;

        global $INPUT;
        if (!$INPUT->bool('u')) return;
        $response = $this->generateResponse($event);

        if (is_null($response) || !$this->validateResponse($response)) {
            $event->result = false;
            $event->preventDefault();
            $event->stopPropagation();
        }
    }
}