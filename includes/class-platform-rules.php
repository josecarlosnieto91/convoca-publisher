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
 * Qué admite cada red, antes de enviar en vez de fallar al enviar.
 *
 * Dos clases de regla, y conviene no mezclarlas:
 *
 * - **Duras** (`chars`): la red rechaza el mensaje si se pasa. Se recorta antes de mandarlo.
 * - **Recomendadas** (`hashtags`, `urls`): no falla, pero el alcance cae. Solo se avisa.
 *
 * `url_weight` es el peso con el que cada red cuenta una URL: X cuenta cualquier enlace como
 * 23 caracteres (los que use su acortador), así que medir el texto tal cual daría un contador
 * mentiroso. 0 significa «cuenta lo que mida».
 */
class Platform_Rules
{
    /**
     * Límites por red. Ver docs/reglas-por-red.md para de dónde sale cada número.
     */
    private const RULES = [
        'twitter' => ['chars' => 280, 'url_weight' => 23, 'hashtags' => 3, 'urls' => 1],
        // Mastodon NO: su API recibe texto plano (el HTML es solo lo que devuelve al leer).
        'mastodon' => ['chars' => 500, 'url_weight' => 0, 'hashtags' => 5, 'urls' => 0, 'bold' => ''],
        'linkedin' => ['chars' => 3000, 'url_weight' => 0, 'hashtags' => 3, 'urls' => 0],
        'facebook' => ['chars' => 63206, 'url_weight' => 0, 'hashtags' => 0, 'urls' => 0],
        'telegram' => ['chars' => 4096, 'url_weight' => 0, 'hashtags' => 0, 'urls' => 0, 'bold' => 'html'],
        'tiktok' => ['chars' => 2200, 'url_weight' => 0, 'hashtags' => 0, 'urls' => 0],
        'googlemybusiness' => ['chars' => 1500, 'url_weight' => 0, 'hashtags' => 0, 'urls' => 1],
    ];

    /**
     * Red desconocida (un canal nuevo): un tope prudente y ninguna regla recomendada.
     */
    private const FALLBACK = ['chars' => 2000, 'url_weight' => 0, 'hashtags' => 0, 'urls' => 0, 'bold' => ''];

    /**
     * @return array{chars: int, url_weight: int, hashtags: int, urls: int}
     */
    public static function rules(string $network): array
    {
        return self::RULES[$network] ?? self::FALLBACK;
    }

    /**
     * Las redes con reglas propias (las demás caen en el tope prudente).
     *
     * @return string[]
     */
    public static function networks(): array
    {
        return array_keys(self::RULES);
    }

    public static function limit(string $network): int
    {
        return self::rules($network)['chars'];
    }

    /**
     * Cuánto ocupa un mensaje para esa red, con el peso de sus enlaces.
     */
    /**
     * Si la red acepta negrita, y cómo.
     *
     * **Solo Telegram**, y solo en su modo HTML: es la única de las redes del plugin que acepta
     * formato en el texto que se le manda (comprobado en su documentación: pasado `HTML` en
     * `parse_mode` admite `<b>`). Mastodon, X, Facebook, LinkedIn, Google y TikTok reciben texto
     * plano, y en Mastodon el HTML es solo lo que devuelve al leer, no lo que acepta al publicar.
     *
     * En las demás, la variable del título en negrita devuelve el título tal cual: mejor eso que un
     * `<b>` a la vista en el mensaje, que fue justo lo que pasó.
     */
    public static function bold_format(string $network): string
    {
        return (string) (self::rules($network)['bold'] ?? '');
    }

    /**
     * El enlace, solo si el mensaje no lo lleva ya.
     *
     * Telegram y Mastodon lo pegaban siempre al final: con una plantilla que ya trae `{url}` —o
     * con el enlace dentro del extracto— el mismo enlace salía dos veces. Visto en producción,
     * con el último post de Lugg.
     */
    public static function url_if_missing(string $message, string $url): string
    {
        if ('' === $url || str_contains($message, $url)) {
            return '';
        }

        return $url;
    }

    public static function count(string $network, string $message): int
    {
        $peso = self::rules($network)['url_weight'];

        if (0 === $peso) {
            return mb_strlen($message);
        }

        $largo  = mb_strlen($message);
        $enlaces = self::urls($message);

        foreach ($enlaces as $enlace) {
            $largo += $peso - mb_strlen($enlace);
        }

        return $largo;
    }

    /**
     * Las URL del mensaje, tal cual aparecen y sin la puntuación que llevan pegada al lado
     * (una coma detrás del enlace no forma parte del enlace, y hace falta que no lo parezca).
     *
     * @return string[]
     */
    public static function urls(string $message): array
    {
        if (!preg_match_all('#\bhttps?://[^\s<>"\']+#i', $message, $encontradas)) {
            return [];
        }

        $limpias = array_map(static fn(string $url): string => rtrim($url, ',.;:!?)]}\'"'), $encontradas[0]);

        return array_values(array_unique(array_filter($limpias)));
    }

    public static function hashtag_count(string $message): int
    {
        // Un enlace puede llevar una almohadilla dentro (`…/pagina#seccion`) y eso no es una etiqueta.
        $sin_enlaces = str_replace(self::urls($message), '', $message);

        return (int) preg_match_all('/#[\p{L}\p{N}_]+/u', $sin_enlaces);
    }

    /**
     * ¿Se puede enviar? Devuelve el recuento y los avisos, con el texto ya listo.
     *
     * @return array{ok: bool, fit: bool, chars: int, limit: int, problems: string[], warnings: string[], message: string}
     */
    public static function check(string $network, string $message): array
    {
        $reglas   = self::rules($network);
        $chars    = self::count($network, $message);
        $fit      = $chars <= $reglas['chars'];
        $problems = [];
        $warnings = [];

        if (!$fit) {
            $problems[] = sprintf(
                /* translators: 1: caracteres que tiene, 2: límite de la red, 3: caracteres de más */
                __('No cabe en %1$s caracteres: tiene %2$d (%3$d de más).', 'convoca-publisher'),
                number_format_i18n($reglas['chars']),
                $chars,
                $chars - $reglas['chars']
            );
        }

        if ($reglas['hashtags'] > 0 && self::hashtag_count($message) > $reglas['hashtags']) {
            $warnings[] = sprintf(
                /* translators: 1: número de etiquetas que lleva, 2: máximo recomendado */
                __('Lleva %1$d etiquetas; en esta red funcionan mejor hasta %2$d.', 'convoca-publisher'),
                self::hashtag_count($message),
                $reglas['hashtags']
            );
        }

        if ($reglas['urls'] > 0 && count(self::urls($message)) > $reglas['urls']) {
            $warnings[] = sprintf(
                /* translators: 1: número de enlaces que lleva, 2: máximo recomendado */
                __('Lleva %1$d enlaces; lo habitual en esta red es %2$d.', 'convoca-publisher'),
                count(self::urls($message)),
                $reglas['urls']
            );
        }

        return [
            'ok'       => $fit,
            'fit'      => $fit,
            'chars'    => $chars,
            'limit'    => $reglas['chars'],
            'problems' => $problems,
            'warnings' => $warnings,
            'message'  => $fit ? $message : self::trim($network, $message),
        ];
    }

    /**
     * Recortar lo justo para que quepa, sin romper el enlace: se acorta el texto y se deja
     * la primera URL entera al final, que es lo que la gente espera encontrar.
     */
    public static function trim(string $network, string $message): string
    {
        $limite = self::limit($network);

        if (self::count($network, $message) <= $limite) {
            return $message;
        }

        $enlaces = self::urls($message);
        $enlace  = [] === $enlaces ? '' : $enlaces[0];
        $cuerpo  = '' === $enlace ? $message : str_replace($enlace, '', $message);
        $cuerpo  = trim(preg_replace('/\s+/u', ' ', $cuerpo) ?? '');

        if ('' === $enlace) {
            return rtrim(mb_substr($cuerpo, 0, max(1, $limite - 1))) . '…';
        }

        // Con enlace: texto recortado + «…» + el enlace (una sola vez, al final).
        $hueco  = $limite - self::peso($network, $enlace) - 2;
        $cuerpo = rtrim(mb_substr($cuerpo, 0, max(1, $hueco)));

        return trim($cuerpo) . '… ' . $enlace;
    }

    /**
     * Lo que ocupa un enlace en esa red (peso fijo si lo tiene, su longitud si no).
     */
    private static function peso(string $network, string $url): int
    {
        $peso = self::rules($network)['url_weight'];

        return 0 === $peso ? mb_strlen($url) : $peso;
    }
}
