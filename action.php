<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Logger;

class action_plugin_validator extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'injectScript');
        
        $controller->register_hook('FORM_EDIT_OUTPUT', 'BEFORE', $this, 'insertHTML', ['edit-form']);
        $controller->register_hook('FORM_RESENDPWD_OUTPUT', 'BEFORE', $this, 'insertHTML', ['reset-password']);
        $controller->register_hook('FORM_REGISTER_OUTPUT', 'BEFORE', $this, 'insertHTML', ['register']);
        $controller->register_hook('FORM_LOGIN_OUTPUT', 'BEFORE', $this, 'insertHTML', ['login']);

        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'verifyForm');
        $controller->register_hook('AUTH_LOGIN_CHECK', 'BEFORE', $this, 'verifyLogin');
    }

    public function injectScript(Event $event, $param)
    {
        $helper = plugin_load('helper', 'validator');
        $provider = $helper->getProvider();

        $challenge = $provider['challenge_script'] ?? '';
        
        $event->data['script'][] = [
            'type'      => 'text/javascript',
            'src'       => $challenge,
            'async'     => 'async',
            'defer'     => 'defer',
            '_data'     => ''
        ];
    }

    public function insertHTML(Event $event, $param)
    {
        $type = $param[0] ?? false;
        if (!$type) return;

        $helper = plugin_load('helper', 'validator');
        if (!$helper->isEnabled($type) || !$html = $helper->getHTML()) return;

        $form = $event->data;

        if (!is_a($form, \dokuwiki\Form\Form::class)) return;

        $pos = $form->findPositionByAttribute('type', 'submit');
        $form->addHTML($html, $pos);
    }

    private function validEvent(string $act): bool
    {
        global $INPUT;

        $type = null;
        $requiresSaveInput = false;

        switch ($act) {
            case 'save':
                $type = 'edit-form';
                break;
            case 'register':
                $type = 'register';
                $requiresSaveInput = true;
                break;
            case 'resendpwd':
                $type = 'reset-password';
                $requiresSaveInput = true;
                break;
            default:
                return false;
        }

        if ($requiresSaveInput && !$INPUT->bool('save')) {
            return false;
        }

        $helper = plugin_load('helper', 'validator');
        return $helper->isEnabled($type);
    }

    public function verifyForm(Event $event, $param)
    {
        global $INPUT;
        $act = act_clean($event->data);

        if (!$this->validEvent($act)) return;

        $helper = plugin_load('helper', 'validator');        
        if (!$helper->check())
        {
            if ($act == 'save')
            {
                $event->data = 'preview';
            } else {
                $INPUT->post->set('save', false);
            }
        }
    }

    public function verifyLogin(Event $event, $param)
    {
        global $INPUT;
        if (!$INPUT->bool('u')) return;

        $helper = plugin_load('helper', 'validator');

        if (!$helper->isEnabled('login')) return;

        if (!$helper->check())
        {
            $event->result = false;
            $event->preventDefault();
            $event->stopPropagation();
        }
    }
}
