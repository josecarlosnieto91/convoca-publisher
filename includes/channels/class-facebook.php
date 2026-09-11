<?php

/**
 * Convoca Publisher
 *
 * @package    Convoca\Publisher
 * @subpackage Channels
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

namespace ConvocaPublisher\Channels;

defined('ABSPATH') || exit;

class Facebook implements ChannelInterface
{
    public function get_id(): string
    {
        return 'facebook';
    }

    public function get_name(): string
    {
        return __('Facebook / Instagram', 'convoca-publisher');
    }

    public function is_available(): bool
    {
        $token = $this->get_token();
        $page_id = $this->get_page_id();
        return !empty($token) && !empty($page_id);
    }

    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
    {
        $token = $this->get_token();
        $page_id = $this->get_page_id();

        if (empty($token) || empty($page_id)) {
            return ['success' => false, 'error' => __('Token o Page ID no configurados.', 'convoca-publisher')];
        }

        $api_url = "https://graph.facebook.com/v22.0/{$page_id}/feed";

        $data = [
            'message'       => $message,
            'link'          => $url,
            'access_token'  => $token,
        ];

        if (!empty($image_url)) {
            $data['picture'] = $image_url;
        }

        $response = wp_remote_post($api_url, [
            'body'    => $data,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code < 200 || $http_code >= 300 || !isset($body['id'])) {
            $error_msg = $body['error']['message'] ?? __('Error desconocido de Meta API.', 'convoca-publisher');
            return ['success' => false, 'error' => $error_msg];
        }

        // El muro ya está publicado. Instagram va DESPUÉS y por su cuenta: si falla, esto
        // sigue siendo un envío correcto —reintentarlo duplicaría la publicación en
        // Facebook— y lo que se hace es contarlo.
        $redes  = 'facebook';
        $aviso  = '';

        if ($this->instagram_linked()) {
            $instagram = $this->publish_instagram($message, $image_url);

            if (!empty($instagram['success'])) {
                $redes .= '+instagram';
            } else {
                $aviso = sprintf(
                    /* translators: %s: motivo por el que no se pudo publicar en Instagram */
                    __('Instagram: %s', 'convoca-publisher'),
                    (string) ($instagram['error'] ?? '')
                );
            }
        }

        return [
            'success'  => true,
            'post_id'  => $body['id'],
            'networks' => $redes,
            'notice'   => $aviso,
        ];
    }

    /**
     * Publica en Instagram: primero el contenedor de medios, después la publicación.
     *
     * Instagram exige imagen en toda publicación, así que sin imagen destacada no se
     * intenta: se dice por qué en vez de mandar una petición que Meta va a rechazar.
     *
     * Los pies de foto se recortan al valor de reserva del plugin (2000) porque el límite
     * exacto de Meta no aparece en la documentación consultada (2026-09-11) y prefiero
     * quedarme corto que perder la publicación.
     *
     * @param string $message   Texto ya montado.
     * @param string $image_url Imagen destacada (vacío = no hay).
     * @return array{success: bool, error?: string}
     */
    private function publish_instagram(string $message, string $image_url): array
    {
        $ig_id = (string) get_option('convoca_publisher_instagram_business_id', '');
        $token = $this->get_token();

        if ('' === $ig_id) {
            return ['success' => false, 'error' => __('falta el ID de la cuenta', 'convoca-publisher')];
        }

        if ('' === $image_url) {
            return [
                'success' => false,
                'error'   => __('la entrada no tiene imagen destacada, y Instagram no admite publicaciones sin imagen', 'convoca-publisher'),
            ];
        }

        $limite    = \ConvocaPublisher\Platform_Rules::limit('instagram');
        $pie       = mb_substr($message, 0, $limite);
        $contenedor = wp_remote_post(
            "https://graph.facebook.com/v22.0/{$ig_id}/media",
            [
                'timeout' => 30,
                'body'    => [
                    'image_url'    => $image_url,
                    'caption'      => $pie,
                    'access_token' => $token,
                ],
            ]
        );

        if (is_wp_error($contenedor)) {
            return ['success' => false, 'error' => $contenedor->get_error_message()];
        }

        $cuerpo = json_decode(wp_remote_retrieve_body($contenedor), true);
        $codigo = wp_remote_retrieve_response_code($contenedor);
        $id     = (string) ($cuerpo['id'] ?? '');

        if ($codigo < 200 || $codigo >= 300 || '' === $id) {
            return ['success' => false, 'error' => (string) ($cuerpo['error']['message'] ?? $codigo)];
        }

        $publicacion = wp_remote_post(
            "https://graph.facebook.com/v22.0/{$ig_id}/media_publish",
            [
                'timeout' => 30,
                'body'    => [
                    'creation_id'  => $id,
                    'access_token' => $token,
                ],
            ]
        );

        if (is_wp_error($publicacion)) {
            return ['success' => false, 'error' => $publicacion->get_error_message()];
        }

        $respuesta = json_decode(wp_remote_retrieve_body($publicacion), true);
        $estado    = wp_remote_retrieve_response_code($publicacion);

        if ($estado < 200 || $estado >= 300 || empty($respuesta['id'])) {
            return [
                'success' => false,
                'error'   => (string) ($respuesta['error']['message'] ?? __('Meta no confirmó la publicación', 'convoca-publisher')),
            ];
        }

        return ['success' => true];
    }

    public function get_settings_fields(): array
    {
        return [
            'convoca_publisher_facebook_token' => [
                'title'       => __('Token de Acceso (Page Access Token)', 'convoca-publisher'),
                'type'        => 'password',
                'description' => __('Token de página de Facebook con permisos pages_manage_posts y pages_read_engagement.', 'convoca-publisher'),
            ],
            'convoca_publisher_facebook_page_id' => [
                'title'       => __('ID de la Página de Facebook', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('ID numérico de tu página de Facebook.', 'convoca-publisher'),
            ],
            'convoca_publisher_instagram_business_id' => [
                'title'       => __('ID de Instagram Business (opcional)', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('ID de la cuenta profesional de Instagram vinculada a la Página. Con esto se comprueba el permiso y la cuota, Se publica en ella con la API (contenedor y publicación) además del muro de la Página. Instagram exige imagen: sin imagen destacada, esa publicación se salta y se dice en el historial.', 'convoca-publisher'),
            ],
            // Plantilla de mensaje específica para este canal
            'convoca_publisher_facebook_template' => [
                'title'       => __('Plantilla del mensaje', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('{title}, {excerpt}, {url}, {hashtags}, {date}, {author}. Por defecto: {title} — {url} {hashtags}', 'convoca-publisher'),
            ],
        ];
    }

    public function validate_settings(array $settings): array
    {
        $errors = [];
        if (empty($settings['convoca_publisher_facebook_token'])) {
            $errors[] = __('El token de Facebook es obligatorio.', 'convoca-publisher');
        }
        if (empty($settings['convoca_publisher_facebook_page_id'])) {
            $errors[] = __('El ID de página de Facebook es obligatorio.', 'convoca-publisher');
        }
        return $errors;
    }

    public function get_token(): string
    {
        return get_option('convoca_publisher_facebook_token', '');
    }

    private function get_page_id(): string
    {
        return get_option('convoca_publisher_facebook_page_id', '');
    }

    private function instagram_linked(): bool
    {
        return !empty(get_option('convoca_publisher_instagram_business_id', ''));
    }

    /**
     * ¿Se puede publicar en esa cuenta de Instagram? Se le pregunta a Meta por la cuota de
     * publicación, que es el endpoint que confirma las tres cosas a la vez: que el ID existe,
     * que el token lleva `instagram_content_publish` y cuánto queda del límite de 24 h.
     *
     * @return string Una frase para la pantalla, ya traducida.
     */
    private function instagram_state(): string
    {
        $ig_id = (string) get_option('convoca_publisher_instagram_business_id', '');

        $resp = wp_remote_get(
            "https://graph.facebook.com/v22.0/{$ig_id}/content_publishing_limit?access_token=" . rawurlencode($this->get_token()),
            ['timeout' => 15]
        );

        if (is_wp_error($resp)) {
            return sprintf(
                /* translators: %s: error message */
                __('❌ Instagram: no se pudo preguntar a Meta (%s)', 'convoca-publisher'),
                $resp->get_error_message()
            );
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $code = wp_remote_retrieve_response_code($resp);

        if ($code < 200 || $code >= 300) {
            return sprintf(
                /* translators: %s: error message from Meta */
                __('❌ Instagram: ese ID no responde con este token (%s)', 'convoca-publisher'),
                $body['error']['message'] ?? $code
            );
        }

        $cuota = (int) ($body['data'][0]['quota_usage'] ?? 0);

        return sprintf(
            /* translators: %d: publicaciones hechas en las últimas 24 horas */
            __('✅ Instagram responde (publicadas en 24 h: %d de 100). La publicación directa por API aún no está implementada: hoy lo que llega a Instagram lo lleva el cross-post de la Página.', 'convoca-publisher'),
            $cuota
        );
    }

    public function verify_connection(): array
    {
        $token = $this->get_token();
        $page_id = $this->get_page_id();

        if (empty($token) || empty($page_id)) {
            return ['success' => false, 'message' => __('Token o Page ID no configurados.', 'convoca-publisher')];
        }

        $resp = wp_remote_get("https://graph.facebook.com/v22.0/{$page_id}?fields=name&access_token={$token}", [
            'timeout' => 15,
        ]);

        if (is_wp_error($resp)) {
            return ['success' => false, 'message' => __('Error de conexión: ', 'convoca-publisher') . $resp->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $http_code = wp_remote_retrieve_response_code($resp);

        if ($http_code >= 200 && $http_code < 300 && isset($body['name'])) {
            $msg = sprintf(
                /* translators: %s: Facebook page name */
                __('✅ Conexión correcta. Página: %s', 'convoca-publisher'),
                $body['name']
            );
            if ($this->instagram_linked()) {
                // No basta con que el campo tenga algo: se le pregunta a Meta. Si el ID no
                // responde o el token no trae `instagram_content_publish`, hay que saberlo AQUÍ
                // y no descubrirlo con una publicación perdida.
                $msg .= ' | ' . $this->instagram_state();
            }

            return ['success' => true, 'message' => $msg];
        }

        $error_msg = $body['error']['message'] ?? __('Error desconocido de Meta API.', 'convoca-publisher');
        return ['success' => false, 'message' => $error_msg];
    }
}
