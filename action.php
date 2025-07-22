<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

class action_plugin_validator extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'injectScript');
        $controller->register_hook('FORM_REGISTER_OUTPUT', 'BEFORE', $this, 'handleFormOutput');
        $controller->register_hook('FORM_LOGIN_OUTPUT', 'BEFORE', $this, 'handleFormOutput');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleRegister');
        $controller->register_hook('AUTH_LOGIN_CHECK', 'BEFORE', $this, 'handleLogin');
    }

    public function injectScript(Event $event, $param)
    {
        $helper = plugin_load('helper', 'validator');
        $provider = $helper->getProvider();
        
        $event->data['script'][] = [
            'type'      => 'text/javascript',
            'src'       => $provider['challenge_script'],
            'async'     => 'async',
            'defer'     => 'defer',
            '_data'     => ''
        ];
    }

    public function handleFormOutput(Event $event, $param)
    {
        $helper = plugin_load('helper', 'validator');
        if (!$html = $helper->getHTML()) return;

        $form = $event->data;

        $pos = $form->findPositionByAttribute('type', 'submit');
        $form->addHTML($html, $pos);
    }

    public function handleRegister(Event $event, $param)
    {
        global $INPUT;
        $act = act_clean($event->data);
        if ($act !== 'register' || !$INPUT->bool('save')) return;

        $helper = plugin_load('helper', 'validator');
        if (!$helper->check())
        {
            $INPUT->post->set('save', false);
        }
    }

    public function handleLogin(Event $event, $param)
    {
        global $INPUT;
        if (!$INPUT->bool('u')) return;

        $helper = plugin_load('helper', 'validator');
        if (!$helper->check())
        {
            $event->result = false;
            $event->preventDefault();
            $event->stopPropagation();
        }
    }
}
