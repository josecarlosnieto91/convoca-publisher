<?php

/**
 * Convoca Publisher — una cuenta concreta de una red.
 *
 * @package    Convoca\Publisher
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

/**
 * Perfil: una cuenta de una red, con sus credenciales y su plantilla.
 *
 * Envuelve al canal de la red y **pone sus ajustes en su sitio mientras se le llama**:
 * antes de publicar o verificar, se interceptan las lecturas de esos ajustes
 * (`pre_option_*`) para que devuelvan los de esta cuenta, y al terminar se retiran.
 * Así los canales siguen leyendo sus opciones como siempre y no hay que tocar ninguno
 * de los siete.
 */
final class Channel_Profile implements Channels\ChannelInterface
{
    /** @var array<string, mixed> */
    private array $profile;

    private Channels\ChannelInterface $channel;

    /**
     * @param array<string, mixed> $profile Perfil tal y como lo devuelve el almacén.
     */
    public function __construct(array $profile, Channels\ChannelInterface $channel)
    {
        $this->profile = $profile;
        $this->channel = $channel;
    }

    /**
     * Id del perfil (es el que usan la cola, el historial y el editor).
     */
    public function get_id(): string
    {
        return (string) $this->profile['id'];
    }

    /**
     * Nombre visible de la cuenta («Telegram — Centro Social»).
     */
    public function get_name(): string
    {
        return (string) $this->profile['name'];
    }

    /**
     * Red a la que pertenece («telegram», «facebook»…).
     */
    public function get_channel_id(): string
    {
        return (string) $this->profile['channel'];
    }

    /**
     * Plantilla propia de esta cuenta (vacío = usar la global).
     */
    public function get_template(): string
    {
        return (string) ($this->profile['template'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function get_profile(): array
    {
        return $this->profile;
    }

    /**
     * Credenciales de esta cuenta (para la pantalla de administración).
     *
     * @return array<string, string>
     */
    public function get_settings(): array
    {
        return (array) ($this->profile['settings'] ?? []);
    }

    public function is_available(): bool
    {
        return (bool) $this->with_profile_settings(fn(): bool => $this->channel->is_available());
    }

    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
    {
        return (array) $this->with_profile_settings(
            fn(): array => $this->channel->publish($post_id, $message, $url, $image_url)
        );
    }

    public function get_settings_fields(): array
    {
        return $this->channel->get_settings_fields();
    }

    public function validate_settings(array $settings): array
    {
        return $this->channel->validate_settings($settings);
    }

    public function verify_connection(): array
    {
        return (array) $this->with_profile_settings(fn(): array => $this->channel->verify_connection());
    }

    /**
     * Ejecutar algo del canal con los ajustes de esta cuenta en su sitio.
     */
    private function with_profile_settings(callable $callback): mixed
    {
        $ganchos = [];

        foreach ($this->get_settings() as $option => $value) {
            $gancho = static fn(): string => (string) $value;

            add_filter('pre_option_' . $option, $gancho, 100);
            $ganchos[] = ['option' => $option, 'callback' => $gancho];
        }

        try {
            return $callback();
        } finally {
            foreach ($ganchos as $gancho) {
                remove_filter('pre_option_' . $gancho['option'], $gancho['callback'], 100);
            }
        }
    }
}
