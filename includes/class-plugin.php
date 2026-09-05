<?php

/**
 * Convoca Publisher
 *
 * @package    Convoca\Publisher
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */


namespace ConvocaPublisher;

defined('ABSPATH') || exit;

// @phpstan-ignore-next-line (constant resolves to local path)
// Classes auto-loaded via Composer classmap. Run `composer dump-autoload --optimize` after adding new files.

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
            'convoca_publisher_facebook_token',
            'convoca_publisher_linkedin_token',
            'convoca_publisher_twitter_bearer_token',
            'convoca_publisher_tiktok_token',
            'convoca_publisher_gmb_token',
            'convoca_publisher_telegram_token',
            'convoca_publisher_mastodon_token',
        ];

        add_action('pre_update_option', [Crypto::class, 'encrypt_on_save'], 10, 2);

        foreach ($token_options as $option) {
            add_filter("option_{$option}", [Crypto::class, 'decrypt_on_load'], 10, 2);
        }
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('convoca-publisher', false, basename(CONVOCA_PUBLISHER_PLUGIN_DIR) . '/languages');
    }

    public function handle_privacy_ack(): void
    {
        if (isset($_POST['convoca_publisher_privacy_ack']) && current_user_can('manage_options')) {
            update_option('convoca_publisher_privacy_acknowledged', (bool) $_POST['convoca_publisher_privacy_ack']);
        }
    }

    private function load_channels(): void
    {
        $channel_files = glob(CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-*.php');
        foreach ($channel_files as $file) {
            $class_name = basename($file, '.php');
            $class_name = str_replace('class-', 'ConvocaPublisher\\Channels\\', $class_name);
            $class_name = str_replace('-', '_', $class_name);
            if (class_exists($class_name)) {
                $channel = new $class_name();
                $this->channels[$channel->get_id()] = $channel;
            }
        }
    }

    public function get_channels(): array
    {
        return $this->channels;
    }

    /**
     * Get a channel instance by its ID.
     *
     * @param string $id The channel ID (e.g., 'facebook', 'telegram').
     * @return Channels\ChannelInterface|null
     */
    public function get_channel(string $id): ?Channels\ChannelInterface
    {
        return $this->channels[$id] ?? null;
    }
}
