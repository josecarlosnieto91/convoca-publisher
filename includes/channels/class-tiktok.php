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

defined( 'ABSPATH' ) || exit;

class Tiktok implements ChannelInterface {

	public function get_id(): string {
		return 'tiktok';
	}

	public function get_name(): string {
		return __( 'TikTok', 'convoca-publisher' );
	}

	public function is_available(): bool {
		return ! empty( get_option( 'convoca_publisher_tiktok_token', '' ) ) && ! empty( get_option( 'convoca_publisher_tiktok_open_id', '' ) );
	}

	public function publish( int $post_id, string $message, string $url, string $image_url = '' ): array {
		$token   = get_option( 'convoca_publisher_tiktok_token', '' );
		$open_id = get_option( 'convoca_publisher_tiktok_open_id', '' );

		if ( empty( $token ) || empty( $open_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Token u Open ID de TikTok no configurados.', 'convoca-publisher' ),
			);
		}

		$post_url = $url;
		$title    = html_entity_decode( wp_trim_words( $message, 15 ) );

		$body = array(
			'open_id'      => $open_id,
			'access_token' => $token,
			'post_info'    => array(
				'title'                => mb_substr( $title, 0, 100 ),
				'privacy_level'        => 'PUBLIC',
				'disable_duet'         => false,
				'disable_stitch'       => false,
				'disable_comment'      => false,
				'brand_organic_opt_in' => false,
			),
			'source_info'  => array(
				'source'    => 'PULL_FROM_URL',
				'video_url' => '',
			),
		);

		$response = wp_remote_post(
			'https://open-api.tiktok.com/share/video/publish/',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$resp_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $http_code >= 200 && $http_code < 300 && isset( $resp_body['data']['publish_id'] ) ) {
			return array(
				'success' => true,
				'post_id' => $resp_body['data']['publish_id'],
			);
		}

		$error = $resp_body['message'] ?? $resp_body['data']['error']['message'] ?? __( 'TikTok no ha explicado el fallo. Reintenta la publicacion; si se repite, revisa las credenciales del canal en Ajustes.', 'convoca-publisher' );
		return array(
			'success' => false,
			'error'   => $error,
		);
	}

	public function get_settings_fields(): array {
		return array(
			'convoca_publisher_tiktok_token'    => array(
				'title'       => __( 'Access Token', 'convoca-publisher' ),
				'type'        => 'password',
				'description' => __( 'Token OAuth 2.0 de TikTok con permisos video.publish.', 'convoca-publisher' ),
			),
			'convoca_publisher_tiktok_open_id'  => array(
				'title'       => __( 'Open ID', 'convoca-publisher' ),
				'type'        => 'text',
				'description' => __( 'Open ID del usuario de TikTok que publicará.', 'convoca-publisher' ),
			),
			'convoca_publisher_tiktok_template' => array(
				'title'       => __( 'Título del video', 'convoca-publisher' ),
				'type'        => 'text',
				'description' => __( '{title} — máximo 100 caracteres. Por defecto: {title}', 'convoca-publisher' ),
			),
		);
	}

	public function validate_settings( array $settings ): array {
		$errors = array();
		if ( empty( $settings['convoca_publisher_tiktok_token'] ) ) {
			$errors[] = __( 'El token de TikTok es obligatorio.', 'convoca-publisher' );
		}
		if ( empty( $settings['convoca_publisher_tiktok_open_id'] ) ) {
			$errors[] = __( 'El Open ID de TikTok es obligatorio.', 'convoca-publisher' );
		}
		return $errors;
	}

	public function verify_connection(): array {
		$token   = get_option( 'convoca_publisher_tiktok_token', '' );
		$open_id = get_option( 'convoca_publisher_tiktok_open_id', '' );

		if ( empty( $token ) || empty( $open_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Token u Open ID de TikTok no configurados.', 'convoca-publisher' ),
			);
		}

		// Validate token format: should be a reasonable length JWT-like string
		if ( strlen( $token ) < 20 || strlen( $token ) > 512 ) {
			return array(
				'success' => false,
				'message' => __( 'El token de TikTok no tiene un formato válido (longitud incorrecta).', 'convoca-publisher' ),
			);
		}

		// Validate Open ID format: should be a non-empty alphanumeric string
		if ( strlen( $open_id ) < 10 || strlen( $open_id ) > 128 ) {
			return array(
				'success' => false,
				'message' => __( 'El Open ID de TikTok no tiene un formato válido.', 'convoca-publisher' ),
			);
		}

		// El formato se valida antes de gastar una petición, pero **no sustituye** a preguntar a la API: un token
		// inventado de 40 caracteres y un open_id de 14 pasaban por buenos y la pantalla daba ✅ de conexión
		// correcta. El fallo aparecía en la primera publicación real, con el canal ya dado por bueno — peor que no
		// verificar, porque da confianza falsa (publisher#11).
		$resp = wp_remote_get(
			'https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return array(
				'success' => false,
				'message' => __( 'Error de conexión con TikTok: ', 'convoca-publisher' ) . $resp->get_error_message(),
			);
		}

		$body        = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		$http_code   = (int) wp_remote_retrieve_response_code( $resp );
		$codigo      = isset( $body['error']['code'] ) ? (string) $body['error']['code'] : '';
		$open_id_api = isset( $body['data']['user']['open_id'] ) ? (string) $body['data']['user']['open_id'] : '';

		if ( $http_code >= 200 && $http_code < 300 && 'ok' === $codigo && '' !== $open_id_api ) {
			// El token tiene que ser del **mismo** usuario que se configuró: si no, el canal publicaría en la
			// cuenta de otro y el error solo se vería al publicar.
			if ( $open_id_api !== (string) $open_id ) {
				return array(
					'success' => false,
					'message' => __( 'El token de TikTok pertenece a otra cuenta distinta del Open ID configurado.', 'convoca-publisher' ),
				);
			}

			$nombre = isset( $body['data']['user']['display_name'] ) ? (string) $body['data']['user']['display_name'] : '';

			return array(
				'success' => true,
				'message' => '' !== $nombre
					/* translators: %s: TikTok display name. */
					? sprintf( __( 'Conexión correcta con TikTok (%s).', 'convoca-publisher' ), $nombre )
					: __( 'Conexión correcta con TikTok.', 'convoca-publisher' ),
			);
		}

		$detalle = isset( $body['error']['message'] ) && '' !== (string) $body['error']['message']
			? (string) $body['error']['message']
			: sprintf( /* translators: %d: HTTP status code. */ __( 'respuesta HTTP %d', 'convoca-publisher' ), $http_code );

		return array(
			'success' => false,
			'message' => __( 'TikTok rechazó la conexión: ', 'convoca-publisher' ) . $detalle,
		);
	}
}
