<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helper/ProvidersHelper.php';

// WordPress output/query format constants
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

// WordPress time constants used across the plugin
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

// plugin constants normally defined in embed-privacy.php
if (!defined('EPI_EMBED_PRIVACY_BASE')) {
    define('EPI_EMBED_PRIVACY_BASE', dirname(__DIR__) . '/');
}

if (!defined('EPI_EMBED_PRIVACY_FILE')) {
    define('EPI_EMBED_PRIVACY_FILE', dirname(__DIR__) . '/embed-privacy.php');
}

if (!defined('EPI_EMBED_PRIVACY_URL')) {
    define('EPI_EMBED_PRIVACY_URL', 'https://www.example.com/wp-content/plugins/embed-privacy/');
}

if (!defined('EMBED_PRIVACY_VERSION')) {
    define('EMBED_PRIVACY_VERSION', '1.13.0');
}
