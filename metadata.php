<?php
$meta['cf-sitekey'] = array('string');
$meta['cf-secretkey'] = array('string');
$meta['g-sitekey'] = array('string');
$meta['g-secretkey'] = array('string');
$meta['mode'] = array(
    'multichoice',
    '_choices' => array(
        'cloudflare',
        'google',
    ),
);