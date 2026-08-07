<?php
/**
 * Plugin Name:       Convoca Publisher
 * Plugin URI:        https://getconvoca.app
 * Description:       Publish WordPress posts to social media channels with customizable templates.
 * Version:           1.4.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Tested up to:      7.0
 * Author:            Jose Carlos Nieto Ramos
 * Author URI:        https://getconvoca.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       convoca-publisher
 * Domain Path:       /languages
 * Requires Plugins:  convoca-core
 */

namespace ConvocaPublisher;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ── Composer autoload ─────────────────────────────── */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
}

// Load translations.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'convoca-publisher', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

/**
 * Ensure convoca-core is active.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'CONVOCA_COMMON_VERSION' ) && ! function_exists( 'convoca_core_is_active' ) ) {
			add_action(
				'admin_notices',
				function () {
					printf(
					    '<div class="notice notice-error"><p><strong>%s:</strong> %s <strong>%s</strong> %s</p></div>',
					    esc_html__('Convoca Publisher', 'convoca-publisher'),
					    esc_html__('Este plugin requiere el plugin', 'convoca-publisher'),
					    esc_html__('Convoca Core', 'convoca-publisher'),
					    esc_html__('activo.', 'convoca-publisher')
					);
				}
			);
			return;
		}
	},
	5
);

define('CP_VERSION', '1.4.0');
define('CP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CP_MIN_PHP', '8.0');
define('CP_MIN_WP', '6.0');

// Comprobación de requisitos al activar
register_activation_hook(__FILE__, 'ConvocaPublisher\\cp_activation_check');
function convoca_publisher_activation_check(): void
{
    global $wp_version;

    if (version_compare(PHP_VERSION, CP_MIN_PHP, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                /* translators: %1$s: minimum required PHP version, %2$s: current PHP version */
                esc_html__('Convoca Publisher requiere PHP %1$s o superior. Tu versión: %2$s', 'convoca-publisher'),
                esc_html(CP_MIN_PHP),
                PHP_VERSION
            )
        );
    }

    if (version_compare($wp_version, CP_MIN_WP, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                /* translators: %s: minimum required WordPress version */
                esc_html__('Convoca Publisher requiere WordPress %s o superior.', 'convoca-publisher'),
                esc_html(CP_MIN_WP)
            )
        );
    }

    // Migración: renombrar opciones antiguas sp_* → cp_*
    convoca_publisher_migrate_legacy_options();
}

// Desactivación
register_deactivation_hook(__FILE__, 'convoca_publisher_deactivation');
function convoca_publisher_deactivation(): void
{
    wp_clear_scheduled_hook('convoca_publisher_retry_failed_posts');
    wp_clear_scheduled_hook('convoca_publisher_retry_process');
}

/**
 * Migrar opciones heredadas a convoca_publisher_*
 */
function convoca_publisher_migrate_legacy_options(): void
{
    // No hay instalaciones antiguas: sp_* y cp_* nunca existieron en producción.
    // Este método se mantiene vacío como guarda contra futuras migraciones.
    // Metadatos de posts (por si existieran en algún entorno de desarrollo)
    global $wpdb;
    $wpdb->query(
        "UPDATE {$wpdb->postmeta} SET meta_key = 'convoca_publisher_published' WHERE meta_key IN ('_sp_published', '_convoca_publisher_published')"
    );
    $wpdb->query(
        "UPDATE {$wpdb->postmeta} SET meta_key = 'convoca_publisher_publish_results' WHERE meta_key IN ('_sp_publish_results', '_convoca_publisher_publish_results')"
    );
    $wpdb->query(
        "UPDATE {$wpdb->postmeta} SET meta_key = 'convoca_publisher_scheduled_publish' WHERE meta_key IN ('_sp_scheduled_publish', '_convoca_publisher_scheduled_publish')"
    );
}

// Aviso de privacidad
function convoca_publisher_privacy_notice(): void
{
    echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>' . esc_html__('🔐 Convoca Publisher — Aviso de privacidad', 'convoca-publisher') . '</strong></p>';
    echo '<p>' . esc_html__('Este plugin envía datos (título, extracto, URL, imagen destacada y etiquetas de tus entradas) a APIs de terceros (Meta, LinkedIn, Twitter/X, TikTok, Google, Telegram, Mastodon) cuando publicas contenido en redes sociales. Los tokens de acceso se almacenan cifrados en la base de datos de WordPress.', 'convoca-publisher') . '</p>';
    echo '<p><label><input type="checkbox" name="convoca_publisher_privacy_ack" value="1" ' . checked(get_option('convoca_publisher_privacy_acknowledged', false), true, false) . '> ' . esc_html__('He leído y acepto este aviso', 'convoca-publisher') . '</label></p>';
    echo '</div>';
}

function convoca_publisher(): \ConvocaPublisher\Plugin
{
    static $instance = null;
    if (null === $instance) {
        $instance = new \ConvocaPublisher\Plugin();
    }
    return $instance;
}

convoca_publisher();
