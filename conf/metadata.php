<?php
$meta['cf-sitekey'] = array('string');
$meta['cf-secretkey'] = array('string');
$meta['g-sitekey'] = array('string');
$meta['g-secretkey'] = array('string');
$meta['provider'] = array(
    'multichoice',
    '_choices' => array(
        'cloudflare',
        'google',
    ),
);
$meta['enabled'] = array('onoff');

$meta['login'] = array('onoff');
$meta['register'] = array('onoff');
$meta['edit-form'] = array('onoff');
$meta['reset-password'] = array('onoff');
$meta['third-party'] = array('onoff');