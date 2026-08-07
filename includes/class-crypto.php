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

class Crypto
{
    private const METHOD = 'aes-256-gcm';
    private const KEY_OPTION = 'convoca_publisher_encryption_key';

    /**
     * Cifrar un valor con AES-256-GCM.
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::get_key();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($ciphertext === false) {
            return $plaintext;
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Descifrar un valor cifrado con AES-256-GCM.
     */
    public static function decrypt(string $ciphertext): string
    {
        $key = self::get_key();
        $data = base64_decode($ciphertext, true);
        if ($data === false || strlen($data) < 28) {
            return $ciphertext;
        }

        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $encrypted = substr($data, 28);

        $plaintext = openssl_decrypt($encrypted, self::METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plaintext !== false ? $plaintext : $ciphertext;
    }

    /**
     * Obtener o generar la clave de cifrado a partir de wp_salt().
     */
    private static function get_key(): string
    {
        $key = get_option(self::KEY_OPTION, '');
        if (empty($key)) {
            $key = wp_salt('auth') . wp_salt('secure_auth');
            $key = hash('sha256', $key, true);
            update_option(self::KEY_OPTION, base64_encode($key), true);
        } else {
            $key = base64_decode($key, true);
        }
        return $key;
    }

    /**
     * Hook: cifrar tokens al guardarlos en options.
     * pre_update_option puede pasar arrays (ej: WP-CLI activation).
     */
    public static function encrypt_on_save(mixed $value, string $option): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (!str_ends_with($option, '_token') && !str_ends_with($option, '_bearer_token')) {
            return $value;
        }

        if (empty($value)) {
            return $value;
        }

        if (str_starts_with($value, 'convoca_publisher_enc:')) {
            return $value;
        }

        return 'convoca_publisher_enc:' . self::encrypt($value);
    }

    /**
     * Hook: descifrar tokens al leerlos.
     */
    public static function decrypt_on_load($value, string $option)
    {
        if (!is_string($value) || !str_starts_with($value, 'convoca_publisher_enc:')) {
            return $value;
        }
        return self::decrypt(substr($value, 7));
    }
}
