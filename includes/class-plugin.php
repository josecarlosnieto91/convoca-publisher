<?php

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/channels/interface-channel.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/channels/class-facebook.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/channels/class-linkedin.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/channels/class-twitter.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/channels/class-tiktok.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/channels/class-googlemybusiness.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/channels/class-telegram.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/channels/class-mastodon.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/class-admin.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/class-publisher.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/class-scheduler.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/class-retry.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/class-crypto.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/class-metabox.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/class-notifications.php';
// @phpstan-ignore-next-line (constant resolves to local path)
require_once CP_PLUGIN_DIR . 'includes/class-rest.php';

class Plugin
{
    private array $channels = [];

    public function __construct()
    {
        $this->load_channels();
        Admin::init();
        Publisher::init($this->channels);
        Scheduler::init();
        Retry::init();
        Metabox::init();
        Notifications::init();
        Rest::init();

        // Cifrado automático de tokens (hooks cp_*)
        $this->register_crypto_hooks();

        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Manejar aceptación de aviso de privacidad
        add_action('admin_init', [$this, 'handle_privacy_ack']);
    }

    /**
     * Registrar hooks de cifrado para todas las opciones de token.
     */
    private function register_crypto_hooks(): void
    {
        $token_options = [
            'cp_facebook_token',
            'cp_linkedin_token',
            'cp_twitter_bearer_token',
            'cp_tiktok_token',
            'cp_gmb_token',
            'cp_telegram_token',
            'cp_mastodon_token',
        ];

        add_action('pre_update_option', [Crypto::class, 'encrypt_on_save'], 10, 2);

        foreach ($token_options as $option) {
            add_filter("option_{$option}", [Crypto::class, 'decrypt_on_load'], 10, 2);
        }
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('convoca-publisher', false, basename(CP_PLUGIN_DIR) . '/languages');
    }

    public function handle_privacy_ack(): void
    {
        if (isset($_POST['cp_privacy_ack']) && current_user_can('manage_options')) {
            update_option('cp_privacy_acknowledged', (bool) $_POST['cp_privacy_ack']);
        }
    }

    private function load_channels(): void
    {
        $channel_files = glob(CP_PLUGIN_DIR . 'includes/channels/class-*.php');
        foreach ($channel_files as $file) {
            $class_name = basename($file, '.php');
            $class_name = str_replace('class-', 'ConvocaPublisher\\Channels\\', $class_name);
            $class_name = str_replace('-', '_', $class_name);
            if (class_exists($class_name)) {
                $channel = new $class_name();
                if ($channel->is_available()) {
                    $this->channels[$channel->get_id()] = $channel;
                }
            }
        }
    }

    public function get_channels(): array
    {
        return $this->channels;
    }
}
