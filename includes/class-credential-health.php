<?php

/**
 * Convoca Publisher
 *
 * @package    Convoca\Publisher
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

/**
 * Cuánto le queda a una credencial.
 *
 * Hay redes cuyo token caduca solo (Facebook y LinkedIn, a los 60 días) y el envío empieza a
 * fallar sin que nadie haya tocado nada. Lo que se puede saber con certeza es **cuándo se
 * verificó por última vez** que funcionaba: con eso y lo que dura el token de esa red, se
 * avisa antes de que falle en vez de enterarse por un envío perdido.
 *
 * No inventa fechas: si nunca se ha verificado, lo dice.
 */
class Credential_Health
{
    /**
     * Días que dura la credencial de cada red **tal y como la pide este plugin**: un token que
     * se pega a mano, sin refresco automático. 0 = no caduca por su cuenta.
     *
     * - **Facebook** (60): el token de página de larga duración.
     * - **LinkedIn** (60): el access token.
     * - **TikTok** (1): el access token dura unas 24 h; el de refresco dura un año, pero aquí
     *   se pega el de acceso, así que al día siguiente ya no vale.
     * - **Google My Business** (1): el access token de Google dura una hora; con un día de
     *   margen sobra para saber que está muerto.
     * - **Twitter/X, Telegram y Mastodon** (0): lo que se pega es un token de aplicación o de
     *   bot, que no caduca por su cuenta. Avisar aquí sería una falsa alarma.
     *
     * Los plazos cortos no son un detalle: enseñan que esas dos redes piden un token que hay
     * que renovar a mano cada poco, y es mejor saberlo por un aviso que por un envío perdido.
     */
    private const LIFETIME = [
        'facebook'         => 60,
        'linkedin'         => 60,
        'tiktok'           => 1,
        'googlemybusiness' => 1,
        'twitter'          => 0,
        'telegram'         => 0,
        'mastodon'         => 0,
    ];

    /**
     * Las redes con plazo propio (las demás no caducan; se puede comprobar).
     *
     * @return string[]
     */
    public static function networks(): array
    {
        return array_keys(self::LIFETIME);
    }

    /** A partir de aquí se avisa, para dar tiempo a reconectar sin prisa. */
    public const WARN_DAYS = 45;

    /** Una red sin dato propio se trata como si no caducara (y no se avisa en falso). */
    public static function lifetime(string $network): int
    {
        return self::LIFETIME[$network] ?? 0;
    }

    public static function days_since(int $verified_at, ?int $now = null): int
    {
        if ($verified_at <= 0) {
            return -1;
        }

        return (int) floor((($now ?? time()) - $verified_at) / DAY_IN_SECONDS);
    }

    /**
     * En qué punto está la credencial.
     *
     * @return string 'sin-datos' | 'sin-caducidad' | 'ok' | 'caduca-pronto' | 'caducada'
     */
    public static function state(string $network, int $verified_at, ?int $now = null): string
    {
        if ($verified_at <= 0) {
            return 'sin-datos';
        }

        $dias = self::lifetime($network);

        if ($dias <= 0) {
            return 'sin-caducidad';
        }

        $pasados = self::days_since($verified_at, $now);

        if ($pasados >= $dias) {
            return 'caducada';
        }

        return $pasados >= min(self::WARN_DAYS, $dias) ? 'caduca-pronto' : 'ok';
    }

    /**
     * Lo que se le cuenta a quien lo lee. Cadena vacía = no hay nada que decir.
     */
    public static function message(string $network, int $verified_at, ?int $now = null): string
    {
        $dias    = self::lifetime($network);
        $pasados = self::days_since($verified_at, $now);

        switch (self::state($network, $verified_at, $now)) {
            case 'sin-datos':
                return __('Todavía no se ha comprobado que esta credencial funcione.', 'convoca-publisher');

            case 'caducada':
                return sprintf(
                    /* translators: 1: días desde la última comprobación, 2: días que dura el token */
                    __('Se comprobó hace %1$d días y el token de esta red dura unos %2$d: lo más probable es que ya no valga.', 'convoca-publisher'),
                    $pasados,
                    $dias
                );

            case 'caduca-pronto':
                return sprintf(
                    /* translators: 1: días desde la última comprobación, 2: días que dura el token */
                    __('Se comprobó hace %1$d días; el token de esta red dura unos %2$d y conviene renovarlo.', 'convoca-publisher'),
                    $pasados,
                    $dias
                );

            case 'sin-caducidad':
                return sprintf(
                    /* translators: %d: días desde la última comprobación */
                    __('Credencial de una red que no caduca por su cuenta (comprobada hace %d días).', 'convoca-publisher'),
                    $pasados
                );

            default:
                return '';
        }
    }

    /**
     * ¿Hay que avisar? (para no llenar la pantalla de avisos que no dicen nada)
     */
    public static function needs_attention(string $network, int $verified_at, ?int $now = null): bool
    {
        return in_array(self::state($network, $verified_at, $now), ['caducada', 'caduca-pronto'], true);
    }
}
