<?php

define('ABSPATH', true);
define('CP_PLUGIN_DIR', dirname(__DIR__) . '/');
define('CP_VERSION', '1.3.0');

// Load WordPress function stubs
require_once __DIR__ . '/stubs.php';

// Load plugin files under test
require_once CP_PLUGIN_DIR . 'includes/channels/interface-channel.php';
require_once CP_PLUGIN_DIR . 'includes/channels/class-facebook.php';
require_once CP_PLUGIN_DIR . 'includes/channels/class-linkedin.php';
require_once CP_PLUGIN_DIR . 'includes/channels/class-twitter.php';
require_once CP_PLUGIN_DIR . 'includes/channels/class-tiktok.php';
require_once CP_PLUGIN_DIR . 'includes/channels/class-googlemybusiness.php';
require_once CP_PLUGIN_DIR . 'includes/channels/class-telegram.php';
require_once CP_PLUGIN_DIR . 'includes/channels/class-mastodon.php';
require_once CP_PLUGIN_DIR . 'includes/class-crypto.php';
require_once CP_PLUGIN_DIR . 'includes/class-scheduler.php';
