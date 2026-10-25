<?php

require_once __DIR__ . '/StubMailer.php';
define('ABSPATH', true);
define('CONVOCA_PUBLISHER_PLUGIN_DIR', dirname(__DIR__) . '/');
// La versión del plugin sale de su CABECERA, no de un número escrito a mano aquí: con un valor fijo
// (era 1.4.0 con el plugin en 1.22.x) cualquier prueba sobre el cache-buster de los assets estaba
// midiendo el número del arnés y no el del producto.
$_cp_cabecera = (string) file_get_contents( __DIR__ . '/../convoca-publisher.php' );
preg_match( '/^ \* Version: (.+)$/m', $_cp_cabecera, $_cp_m );
define( 'CONVOCA_PUBLISHER_VERSION', trim( $_cp_m[1] ?? '0.0.0' ) );
unset( $_cp_cabecera, $_cp_m );


// Load WordPress function stubs
require_once __DIR__ . '/StubCoreUtils.php';
require_once __DIR__ . '/stubs.php';

// Global $wpdb usado por la cola de reintentos/moderación.
$GLOBALS['wpdb'] = new wpdb();
$GLOBALS['_cp_test_db'] = ['rows' => [], 'inserts' => []];

// Load plugin files under test
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/interface-channel.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-facebook.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-linkedin.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-twitter.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-tiktok.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-googlemybusiness.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-telegram.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-mastodon.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/class-crypto.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/class-scheduler.php';
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/class-log-view.php';

// Apoyo de las pruebas (canales de mentira, etc.). Va al final: implementa la interfaz de canal.
require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/class-rest.php';

require_once __DIR__ . '/Support/FakeChannel.php';
