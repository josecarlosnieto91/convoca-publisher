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

    private function load_channels(): void
    {
        $this->channels = self::discover_channels();
    }

    /**
     * Nombres de clase de los canales disponibles.
     *
     * El nombre se deriva del fichero respetando su mayúscula real (la misma regla
     * que usa el classmap de Composer) y, además, se recogen las clases del
     * namespace que ya estén declaradas. **Nunca se adivina en minúsculas**: ese
     * fallo dejó al plugin con cero canales, sin poder publicar ni configurar nada.
     *
     * @return string[]
     */
    public static function channel_class_names(): array
    {
        $names = [];

        foreach (glob(CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-*.php') ?: [] as $file) {
            $base  = str_replace(['class-', '.php'], '', basename($file));
            $words = str_replace('-', ' ', $base);

            $names[] = __NAMESPACE__ . '\\Channels\\' . str_replace(' ', '', ucwords($words));
        }

        // Red de seguridad: si un canal ya está declarado (lo cargó el autoloader u
        // otro camino), cuenta igual aunque su nombre no salga del fichero.
        foreach (get_declared_classes() as $class) {
            if (str_starts_with($class, __NAMESPACE__ . '\\Channels\\')) {
                $names[] = $class;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Instancias de todos los canales disponibles.
     *
     * @return array<string, Channels\ChannelInterface>
     */
    public static function discover_channels(): array
    {
        $channels = [];

        foreach (self::channel_class_names() as $class) {
            if (!class_exists($class) || !is_subclass_of($class, Channels\ChannelInterface::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            $channel                    = $reflection->newInstance();
            $channels[$channel->get_id()] = $channel;
        }

        return $channels;
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
