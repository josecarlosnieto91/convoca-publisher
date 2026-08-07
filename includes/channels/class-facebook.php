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

        if ($http_code >= 200 && $http_code < 300 && isset($body['id'])) {
            return [
                'success'  => true,
                'post_id'  => $body['id'],
                'networks' => 'facebook' . ($this->instagram_linked() ? '+instagram' : ''),
            ];
        }

        $error_msg = $body['error']['message'] ?? __('Error desconocido de Meta API.', 'convoca-publisher');
        return ['success' => false, 'error' => $error_msg];
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
                'description' => __('Si tu Instagram está vinculado a la página de Facebook, se publicará también allí.', 'convoca-publisher'),
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
                $msg .= ' | ' . __('Instagram vinculado', 'convoca-publisher');
            }
            return ['success' => true, 'message' => $msg];
        }

        $error_msg = $body['error']['message'] ?? __('Error desconocido de Meta API.', 'convoca-publisher');
        return ['success' => false, 'message' => $error_msg];
    }
}
