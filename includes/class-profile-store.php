<?php

/**
 * Convoca Publisher — perfiles (varias cuentas por red).
 *
 * @package    Convoca\Publisher
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

/**
 * Almacén de perfiles.
 *
 * Un **perfil** es una cuenta concreta de una red: «Telegram — Centro Social»,
 * «Facebook — Página», «Facebook — Grupo». Cada uno guarda sus propias credenciales
 * (con los mismos nombres de ajuste que usaba la red, para que el canal los lea sin
 * enterarse de nada) y su propia plantilla de mensaje.
 *
 * Los tokens se guardan cifrados dentro de la opción, con el mismo formato que el
 * resto del plugin (`convoca_publisher_enc:` + AES-256-GCM). Se cifran aquí a mano
 * porque el cifrado automático de WordPress solo actúa sobre valores de texto que
 * terminen en `_token`, y esto es un array.
 *
 * **Migración**: al pasar de «un token por red» a perfiles, la configuración que ya
 * existía se convierte en **un perfil por red con el id de la red** (`telegram`,
 * `facebook`…). Ese detalle importa: las entradas ya publicadas, la cola de reintentos
 * y el historial apuntan a esos ids, y así siguen resolviendo sin tocar nada. Las
 * opciones antiguas **no se borran** (la marcha atrás es volver a la versión anterior).
 */
final class Profile_Store
{
    /** Opción donde viven todos los perfiles. */
    public const OPTION = 'convoca_publisher_profiles';

    /** Marca de migración hecha (versión del formato). */
    public const MIGRATED_OPTION = 'convoca_publisher_profiles_migrated';

    /** Versión actual del formato de perfiles. */
    public const FORMAT_VERSION = 1;

    /** Cuentas como máximo por red (evitar veinte sin querer). */
    public const LIMIT_PER_NETWORK = 5;

    /**
     * Todos los perfiles, con los tokens ya descifrados.
     *
     * @return array<int, array{id: string, channel: string, name: string, settings: array<string, string>, template: string, created: string}>
     */
    public static function all(): array
    {
        $profiles = get_option(self::OPTION, []);

        if (!is_array($profiles)) {
            return [];
        }

        $limpios = [];

        foreach ($profiles as $profile) {
            if (!is_array($profile) || empty($profile['id']) || empty($profile['channel'])) {
                continue;
            }

            $profile['name']     = (string) ($profile['name'] ?? $profile['id']);
            $profile['template'] = (string) ($profile['template'] ?? '');
            $profile['created']  = (string) ($profile['created'] ?? '');
            $profile['settings'] = self::decrypt_settings((array) ($profile['settings'] ?? []));

            $limpios[] = $profile;
        }

        return $limpios;
    }

    /**
     * Guardar la lista completa de perfiles.
     *
     * @param array<int, array<string, mixed>> $profiles Perfiles sin cifrar.
     */
    public static function save(array $profiles): bool
    {
        $guardar = [];

        foreach ($profiles as $profile) {
            $profile['settings'] = self::encrypt_settings((array) ($profile['settings'] ?? []));
            $guardar[]           = $profile;
        }

        return (bool) update_option(self::OPTION, $guardar, false);
    }

    /**
     * Un perfil por su id.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $profile_id): ?array
    {
        foreach (self::all() as $profile) {
            if ($profile['id'] === $profile_id) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * Perfiles de una red.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for_channel(string $channel_id): array
    {
        return array_values(
            array_filter(self::all(), static fn(array $profile): bool => $profile['channel'] === $channel_id)
        );
    }

    /**
     * Cuántas cuentas tiene ya una red.
     */
    public static function count_for_channel(string $channel_id): int
    {
        return count(self::for_channel($channel_id));
    }

    /**
     * Crear un perfil.
     *
     * @param array<string, string> $settings Ajustes con su nombre completo de opción.
     *
     * @return array<string, mixed>|false El perfil creado, o `false` si no cabe otro.
     */
    public static function create(string $channel_id, string $name, array $settings = [], string $template = '', string $id = ''): array|false
    {
        if (self::count_for_channel($channel_id) >= self::LIMIT_PER_NETWORK) {
            return false;
        }

        $profiles = self::all();
        $profile  = [
            'id'       => '' !== $id ? self::unique_id($id) : self::unique_id(self::suggest_id($channel_id, $name)),
            'channel'  => $channel_id,
            'name'     => '' !== $name ? $name : $channel_id,
            'settings' => $settings,
            'template' => $template,
            'created'  => current_time('mysql'),
        ];

        $profiles[] = $profile;
        self::save($profiles);

        return $profile;
    }

    /**
     * Actualizar un perfil (nombre, ajustes y/o plantilla).
     *
     * @param array<string, mixed> $changes
     */
    public static function update(string $profile_id, array $changes): bool
    {
        $profiles = self::all();
        $tocado   = false;

        foreach ($profiles as $i => $profile) {
            if ($profile['id'] !== $profile_id) {
                continue;
            }

            if (isset($changes['name'])) {
                $profiles[$i]['name'] = (string) $changes['name'];
            }

            if (isset($changes['template'])) {
                $profiles[$i]['template'] = (string) $changes['template'];
            }

            if (isset($changes['settings']) && is_array($changes['settings'])) {
                $profiles[$i]['settings'] = array_merge($profile['settings'], $changes['settings']);
            }

            $tocado = true;
            break;
        }

        return $tocado && self::save($profiles);
    }

    /**
     * Borrar un perfil. Solo afecta a la configuración de esa cuenta: las entradas ya
     * publicadas y el historial se quedan como están.
     */
    public static function delete(string $profile_id): bool
    {
        $profiles = self::all();
        $quedan   = array_values(
            array_filter($profiles, static fn(array $profile): bool => $profile['id'] !== $profile_id)
        );

        if (count($quedan) === count($profiles)) {
            return false;
        }

        return self::save($quedan);
    }

    /**
     * Id libre a partir de uno propuesto (`telegram`, `telegram-2`, …).
     */
    public static function unique_id(string $base): string
    {
        $base = sanitize_title($base);
        $base = '' !== $base ? $base : 'cuenta';

        $ids = array_column(self::all(), 'id');

        if (!in_array($base, $ids, true)) {
            return $base;
        }

        $n = 2;
        while (in_array($base . '-' . $n, $ids, true)) {
            ++$n;
        }

        return $base . '-' . $n;
    }

    /**
     * Id propuesto para una cuenta nueva de una red.
     */
    private static function suggest_id(string $channel_id, string $name): string
    {
        $sufijo = sanitize_title($name);

        if ('' === $sufijo || $sufijo === sanitize_title($channel_id)) {
            return $channel_id;
        }

        return $channel_id . '-' . $sufijo;
    }

    /**
     * Migrar la configuración antigua (un token por red) a perfiles.
     *
     * Se ejecuta una sola vez. Un perfil por red que tuviera algo configurado, con el
     * **id de la red** para no romper lo ya publicado, la cola ni el historial.
     *
     * @param array<string, object> $channels Canales disponibles (para sacar sus campos).
     */
    public static function migrate(array $channels): void
    {
        if ((int) get_option(self::MIGRATED_OPTION, 0) >= self::FORMAT_VERSION) {
            return;
        }

        $profiles = self::all();

        foreach ($channels as $channel_id => $channel) {
            if (self::count_for_channel($channel_id) > 0) {
                continue; // Ya tiene cuentas: no se toca.
            }

            $settings = [];
            $template = '';

            foreach ($channel->get_settings_fields() as $option => $field) {
                $value = (string) get_option($option, '');

                if (str_ends_with($option, '_template')) {
                    $template = $value;

                    continue;
                }

                if ('' !== $value) {
                    $settings[$option] = $value;
                }
            }

            if ([] === $settings) {
                continue; // Nada configurado en esta red: no se inventa una cuenta.
            }

            $profiles[] = [
                'id'       => $channel_id,
                'channel'  => $channel_id,
                'name'     => (string) $channel->get_name(),
                'settings' => $settings,
                'template' => $template,
                'created'  => current_time('mysql'),
            ];
        }

        self::save($profiles);
        update_option(self::MIGRATED_OPTION, self::FORMAT_VERSION, false);
    }

    /**
     * Cifrar los ajustes que sean credenciales.
     *
     * @param array<string, string> $settings
     *
     * @return array<string, string>
     */
    private static function encrypt_settings(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (!self::is_secret($key) || '' === $value || str_starts_with((string) $value, 'convoca_publisher_enc:')) {
                continue;
            }

            $settings[$key] = 'convoca_publisher_enc:' . Crypto::encrypt((string) $value);
        }

        return $settings;
    }

    /**
     * Descifrar los ajustes que sean credenciales.
     *
     * @param array<string, string> $settings
     *
     * @return array<string, string>
     */
    private static function decrypt_settings(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (!self::is_secret($key)) {
                continue;
            }

            $settings[$key] = (string) Crypto::decrypt_on_load($value, (string) $key);
        }

        return $settings;
    }

    /**
     * ¿Este ajuste es una credencial?
     */
    public static function is_secret(string $option): bool
    {
        return str_ends_with($option, '_token') || str_ends_with($option, '_bearer_token');
    }
}
