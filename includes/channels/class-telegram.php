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

class Telegram implements ChannelInterface
{
    public function get_id(): string
    {
        return 'telegram';
    }

    public function get_name(): string
    {
        return __('Telegram', 'convoca-publisher');
    }

    public function is_available(): bool
    {
        return !empty(get_option('cp_telegram_token', '')) && !empty(get_option('cp_telegram_chat_id', ''));
    }

    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
    {
        $token = get_option('cp_telegram_token', '');
        $chat_id = get_option('cp_telegram_chat_id', '');

        if (empty($token) || empty($chat_id)) {
            return ['success' => false, 'error' => __('Token o Chat ID de Telegram no configurados.', 'convoca-publisher')];
        }

        $text = html_entity_decode($message);
        $text .= "\n\n" . $url;

        $parse_mode = get_option('cp_telegram_parse_mode', 'HTML');
        $body = [
            'chat_id'                  => $chat_id,
            'text'                     => mb_substr($text, 0, 4096),
            'parse_mode'               => $parse_mode,
            'disable_web_page_preview' => false,
        ];

        if (!empty($image_url)) {
            $response = wp_remote_post("https://api.telegram.org/bot{$token}/sendPhoto", [
                'body' => [
                    'chat_id'    => $chat_id,
                    'photo'      => $image_url,
                    'caption'    => mb_substr($text, 0, 1024),
                    'parse_mode' => $parse_mode,
                ],
                'timeout' => 30,
            ]);
        } else {
            $response = wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
                'body'    => $body,
                'timeout' => 30,
            ]);
        }

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $resp_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($resp_body['ok'] ?? false) {
            return ['success' => true, 'post_id' => $resp_body['result']['message_id']];
        }

        return ['success' => false, 'error' => $resp_body['description'] ?? __('Error desconocido de Telegram API.', 'convoca-publisher')];
    }

    public function get_settings_fields(): array
    {
        return [
            'cp_telegram_token' => [
                'title'       => __('Token del Bot', 'convoca-publisher'),
                'type'        => 'password',
                'description' => __('Token del bot de Telegram (de @BotFather).', 'convoca-publisher'),
            ],
            'cp_telegram_chat_id' => [
                'title'       => __('Chat ID', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('ID del canal o grupo (negativo para grupos).', 'convoca-publisher'),
            ],
            'cp_telegram_parse_mode' => [
                'title'       => __('Modo de parseo', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('HTML o Markdown (por defecto HTML).', 'convoca-publisher'),
            ],
            'cp_telegram_template' => [
                'title'       => __('Plantilla del mensaje', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('{title}, {excerpt}, {url}, {hashtags}. Por defecto: {title} — {url} {hashtags}', 'convoca-publisher'),
            ],
        ];
    }

    public function validate_settings(array $settings): array
    {
        $errors = [];
        if (empty($settings['cp_telegram_token'])) {
            $errors[] = __('El token de Telegram es obligatorio.', 'convoca-publisher');
        }
        if (empty($settings['cp_telegram_chat_id'])) {
            $errors[] = __('El Chat ID de Telegram es obligatorio.', 'convoca-publisher');
        }
        return $errors;
    }

    public function verify_connection(): array
    {
        $token = get_option('cp_telegram_token', '');
        $chat_id = get_option('cp_telegram_chat_id', '');

        if (empty($token) || empty($chat_id)) {
            return ['success' => false, 'message' => __('Token o Chat ID no configurados.', 'convoca-publisher')];
        }

        $resp = wp_remote_get("https://api.telegram.org/bot{$token}/getMe", ['timeout' => 15]);

        if (is_wp_error($resp)) {
            return ['success' => false, 'message' => __('Error de conexión: ', 'convoca-publisher') . $resp->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($body['ok'] ?? false) {
            $username = $body['result']['username'] ?? 'desconocido';
            return [
                'success' => true,
                'message' => sprintf(
                    /* translators: %s: Telegram bot username */
                    __('✅ Conexión correcta. Bot: @%s', 'convoca-publisher'),
                    $username
                ),
            ];
        }

        return ['success' => false, 'message' => $body['description'] ?? __('Error desconocido', 'convoca-publisher')];
    }
}
