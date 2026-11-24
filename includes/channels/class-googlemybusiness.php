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

class Googlemybusiness implements ChannelInterface {

	/**
	 * Ajustes de la cuenta que se está usando (los pone el perfil antes de llamar).
	 *
	 * @var array<string, string>|null
	 */
	private ?array $settings = null;

	/**
	 * Canal de GMB, opcionalmente con los ajustes de una cuenta concreta.
	 *
	 * @param array<string, string> $settings Ajustes de una cuenta concreta (opcional).
	 */
	public function __construct( array $settings = array() ) {
		if ( array() !== $settings ) {
			$this->settings = $settings;
		}
	}

	/**
	 * Ajustes de la cuenta, cuando el perfil los pasa antes de publicar o verificar.
	 *
	 * @param array<string, string> $settings Ajustes con su nombre completo de opción.
	 */
	public function set_settings( array $settings ): void {
		$this->settings = $settings;
	}

	/**
	 * Un ajuste del canal: el de la cuenta si lo hay y, si no, el global de siempre.
	 *
	 * En una instalación configurada por el panel las credenciales viven en el perfil y las
	 * opciones globales no existen. Leer solo las globales hacía que la clase dijeran «no
	 * configurado» con el canal funcionando (publisher#26).
	 */
	private function setting( string $option ): string {
		$ajustes = $this->settings();

		if ( isset( $ajustes[ $option ] ) ) {
			return (string) $ajustes[ $option ];
		}

		return (string) get_option( $option, '' );
	}

	/**
	 * Ajustes efectivos: los inyectados o, en su defecto, los del primer perfil de GMB.
	 *
	 * @return array<string, string>
	 */
	private function settings(): array {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$token    = (string) get_option( 'convoca_publisher_gmb_token', '' );
		$location = (string) get_option( 'convoca_publisher_gmb_location', '' );

		if ( '' !== $token || '' !== $location ) {
			return array();
		}

		$perfiles       = \ConvocaPublisher\Profile_Store::for_channel( 'googlemybusiness' );
		$this->settings = (array) ( $perfiles[0]['settings'] ?? array() );

		return $this->settings;
	}

	public function get_id(): string {
		return 'googlemybusiness';
	}

	public function get_name(): string {
		return __( 'Google My Business', 'convoca-publisher' );
	}

	public function is_available(): bool {
		$token    = $this->setting( 'convoca_publisher_gmb_token' );
		$refresh  = $this->setting( 'convoca_publisher_gmb_refresh_token' );
		$location = $this->setting( 'convoca_publisher_gmb_location' );

		return '' !== $location && ( '' !== $token || '' !== $refresh );
	}

	public function publish( int $post_id, string $message, string $url, string $image_url = '' ): array {
		$location = $this->setting( 'convoca_publisher_gmb_location' );

		if ( '' === $location ) {
			return array(
				'success' => false,
				'error'   => __( 'La ubicación de GMB no está configurada.', 'convoca-publisher' ),
			);
		}

		$token = $this->access_token();

		if ( '' !== $token['error'] ) {
			return array(
				'success' => false,
				'error'   => $token['error'],
			);
		}

		$imagen = self::prepare_image_url( $image_url );
		$cuerpo = self::local_post_body( $message, $url, $imagen['url'] );
		$envio  = self::enviar_local_post( $location, $token['token'], $cuerpo );

		if ( ! empty( $envio['success'] ) && '' !== $imagen['notice'] ) {
			$envio['notice'] = $imagen['notice'];
		}

		return $envio;
	}

	/**
	 * Crear el anuncio local y traducir la respuesta de Google.
	 *
	 * @param string               $location Location ID.
	 * @param string               $token    Access token.
	 * @param array<string, mixed> $cuerpo   Cuerpo del anuncio.
	 * @return array{success: bool, post_id?: string, error?: string}
	 */
	private static function enviar_local_post( string $location, string $token, array $cuerpo ): array {
		$respuesta = wp_remote_post(
			"https://mybusiness.googleapis.com/v4/{$location}/localPosts",
			array(
				'headers' => array(
					'Authorization' => "Bearer {$token}",
					'Content-Type'  => 'application/json',
				),
				'body'    => (string) wp_json_encode( $cuerpo ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			return array(
				'success' => false,
				'error'   => $respuesta->get_error_message(),
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $respuesta );
		$resp_body = json_decode( (string) wp_remote_retrieve_body( $respuesta ), true );

		if ( 401 === $http_code ) {
			return array(
				'success' => false,
				'error'   => __( 'El token de Google ha caducado o no es válido. Configura el refresh token para que se renueve solo, o vuelve a conectar la cuenta en Ajustes.', 'convoca-publisher' ),
			);
		}

		if ( $http_code >= 200 && $http_code < 300 && isset( $resp_body['name'] ) ) {
			return array(
				'success' => true,
				'post_id' => (string) $resp_body['name'],
			);
		}

		return array(
			'success' => false,
			'error'   => self::api_error( is_array( $resp_body ) ? $resp_body : null, $http_code ),
		);
	}

	/**
	 * Access token utilizable: el guardado o uno nuevo pedido con el refresh token.
	 *
	 * @return array{token: string, error: string}
	 */
	public function access_token(): array {
		$guardado = $this->setting( 'convoca_publisher_gmb_token' );
		$refresh  = $this->setting( 'convoca_publisher_gmb_refresh_token' );
		$client   = $this->setting( 'convoca_publisher_gmb_client_id' );
		$secret   = $this->setting( 'convoca_publisher_gmb_client_secret' );

		if ( '' === $refresh || '' === $client || '' === $secret ) {
			return $this->token_sin_renovacion( $guardado );
		}

		$clave = 'convoca_publisher_gmb_access_' . md5( $refresh . '|' . $client . '|' . $this->setting( 'convoca_publisher_gmb_location' ) );
		$cache = get_transient( $clave );

		if ( is_string( $cache ) && '' !== $cache ) {
			return array(
				'token' => $cache,
				'error' => '',
			);
		}

		$nuevo = self::renovar_token( $refresh, $client, $secret );

		if ( '' !== $nuevo['error'] ) {
			return $nuevo;
		}

		set_transient( $clave, $nuevo['token'], max( 60, $nuevo['vida'] - 60 ) );

		return array(
			'token' => $nuevo['token'],
			'error' => '',
		);
	}

	/**
	 * Token guardado, o el error que explica que falta.
	 *
	 * @param string $guardado Access token del perfil.
	 * @return array{token: string, error: string}
	 */
	private function token_sin_renovacion( string $guardado ): array {
		if ( '' !== $guardado ) {
			return array(
				'token' => $guardado,
				'error' => '',
			);
		}

		return array(
			'token' => '',
			'error' => __( 'GMB no tiene token. Conecta la cuenta en Ajustes.', 'convoca-publisher' ),
		);
	}

	/**
	 * Pedir un access token nuevo a Google con el refresh token.
	 *
	 * @param string $refresh Refresh token.
	 * @param string $client  Client ID.
	 * @param string $secret  Client secret.
	 * @return array{token: string, vida: int, error: string}
	 */
	private static function renovar_token( string $refresh, string $client, string $secret ): array {
		$respuesta = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => $client,
					'client_secret' => $secret,
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh,
				),
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			return array(
				'token' => '',
				'vida'  => 0,
				'error' => __( 'No se pudo renovar el token de Google: ', 'convoca-publisher' ) . $respuesta->get_error_message(),
			);
		}

		$codigo = (int) wp_remote_retrieve_response_code( $respuesta );
		$cuerpo = json_decode( (string) wp_remote_retrieve_body( $respuesta ), true );
		$nuevo  = is_array( $cuerpo ) ? (string) ( $cuerpo['access_token'] ?? '' ) : '';

		if ( 200 !== $codigo || '' === $nuevo ) {
			return array(
				'token' => '',
				'vida'  => 0,
				'error' => self::error_renovacion( $cuerpo ),
			);
		}

		return array(
			'token' => $nuevo,
			'vida'  => (int) ( $cuerpo['expires_in'] ?? 3600 ),
			'error' => '',
		);
	}

	/**
	 * Mensaje cuando Google no entrega el token.
	 *
	 * @param mixed $cuerpo Respuesta de OAuth.
	 */
	private static function error_renovacion( mixed $cuerpo ): string {
		$motivo = is_array( $cuerpo ) ? (string) ( $cuerpo['error_description'] ?? $cuerpo['error'] ?? '' ) : '';

		return __( 'No se pudo renovar el token de Google. Revisa el refresh token, o vuelve a conectar la cuenta en Ajustes.', 'convoca-publisher' )
			. ( '' !== $motivo ? ' (' . $motivo . ')' : '' );
	}

	/**
	 * Mensaje de error de la API con lo que Google sí detalla.
	 *
	 * `error.message` a secas («Request contains an invalid argument.») no dice qué campo
	 * rechaza; el `status` y los `details[].reason` son los que permiten arreglarlo.
	 *
	 * @param array<string, mixed>|null $cuerpo    Cuerpo de la respuesta.
	 * @param int                       $http_code Código HTTP.
	 */
	public static function api_error( ?array $cuerpo, int $http_code ): string {
		$error   = is_array( $cuerpo['error'] ?? null ) ? $cuerpo['error'] : array();
		$mensaje = (string) ( $error['message'] ?? '' );
		$detalle = array();

		if ( ! empty( $error['status'] ) ) {
			$detalle[] = (string) $error['status'];
		}

		foreach ( (array) ( $error['details'] ?? array() ) as $item ) {
			$partes = array_filter(
				array(
					(string) ( $item['reason'] ?? '' ),
					(string) ( $item['field'] ?? '' ),
				)
			);

			if ( array() !== $partes ) {
				$detalle[] = implode( ' · ', $partes );
			}
		}

		if ( '' === $mensaje ) {
			$mensaje = __( 'Google My Business no ha explicado el fallo. Reintenta la publicación; si se repite, revisa las credenciales del canal en Ajustes.', 'convoca-publisher' );
		}

		$sufijo = array() !== $detalle ? ' [' . implode( '; ', array_unique( $detalle ) ) . ']' : '';

		return sprintf( '%s (HTTP %d)%s', $mensaje, $http_code, $sufijo );
	}

	/**
	 * URL de imagen que acepta la API: JPEG o PNG.
	 *
	 * Un `.webp` da 400 INVALID_ARGUMENT sin decir el campo (publisher#24). Se intenta el
	 * original del adjunto, luego una copia JPEG generada al vuelo y, si nada se puede, se
	 * publica sin foto y se avisa en el historial.
	 *
	 * @param string $url URL de la imagen destacada.
	 * @return array{url: string, notice: string}
	 */
	public static function prepare_image_url( string $url ): array {
		if ( '' === $url ) {
			return array(
				'url'    => '',
				'notice' => '',
			);
		}

		$ruta = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( (string) pathinfo( $ruta, PATHINFO_EXTENSION ) );

		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
			return array(
				'url'    => $url,
				'notice' => '',
			);
		}

		$aviso   = __( 'La imagen destacada no es JPEG/PNG; Google no la acepta. Se envió sin foto.', 'convoca-publisher' );
		$adjunto = (int) attachment_url_to_postid( $url );

		if ( $adjunto > 0 ) {
			$original = (string) wp_get_original_image_url( $adjunto );

			if ( '' !== $original ) {
				$ext_original = strtolower( (string) pathinfo( (string) wp_parse_url( $original, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

				if ( in_array( $ext_original, array( 'jpg', 'jpeg', 'png' ), true ) ) {
					return array(
						'url'    => $original,
						'notice' => '',
					);
				}
			}

			$jpeg = self::jpeg_del_adjunto( $adjunto );

			if ( '' !== $jpeg ) {
				return array(
					'url'    => $jpeg,
					'notice' => '',
				);
			}
		}

		return array(
			'url'    => '',
			'notice' => $aviso,
		);
	}

	/**
	 * Copia JPEG del adjunto (se reutiliza si ya existe).
	 *
	 * @param int $adjunto Id del adjunto.
	 */
	private static function jpeg_del_adjunto( int $adjunto ): string {
		$guardada = (string) get_post_meta( $adjunto, '_convoca_publisher_gmb_jpeg', true );

		if ( '' !== $guardada && '' !== (string) get_attached_file( $adjunto ) ) {
			return $guardada;
		}

		$origen = (string) get_attached_file( $adjunto );

		if ( '' === $origen || ! file_exists( $origen ) ) {
			return '';
		}

		$editor = wp_get_image_editor( $origen );

		if ( is_wp_error( $editor ) ) {
			return '';
		}

		$editor->set_quality( 82 );

		$ruta_sin_ext = preg_replace( '/\.[^.]+$/', '', $origen );
		$guardado     = $editor->save( $ruta_sin_ext . '-gmb.jpg', 'image/jpeg' );

		if ( is_wp_error( $guardado ) || empty( $guardado['path'] ) ) {
			return '';
		}

		$subidas = wp_upload_dir();
		$url     = str_replace(
			$subidas['basedir'],
			$subidas['baseurl'],
			(string) $guardado['path']
		);

		if ( '' === $url || $url === (string) $guardado['path'] ) {
			return '';
		}

		update_post_meta( $adjunto, '_convoca_publisher_gmb_jpeg', $url );

		return $url;
	}

	/**
	 * Cuerpo del anuncio local.
	 *
	 * `alertType` solo aplica a EVENT/OFFER con un valor real del enum: mandar
	 * UNSPECIFIED_ALERT_TYPE con STANDARD hace que la API rechace el 100 % de los envíos
	 * («Invalid value at 'local_post.alert_type'», publisher#22).
	 *
	 * @param string $message   Texto.
	 * @param string $url       Enlace.
	 * @param string $image_url Imagen destacada.
	 * @return array<string, mixed>
	 */
	private static function local_post_body( string $message, string $url, string $image_url ): array {
		$body = array(
			'summary'      => mb_substr( html_entity_decode( wp_trim_words( $message, 50 ) ), 0, 1500 ),
			'callToAction' => array(
				'actionType' => 'LEARN_MORE',
				'url'        => $url,
			),
			'topicType'    => 'STANDARD',
		);

		if ( '' !== $image_url ) {
			$body['media'][] = array(
				'mediaFormat' => 'PHOTO',
				'sourceUrl'   => $image_url,
			);
		}

		return $body;
	}

	public function get_settings_fields(): array {
		return array(
			'convoca_publisher_gmb_token'         => array(
				'title'       => __( 'Access Token (OAuth 2.0)', 'convoca-publisher' ),
				'type'        => 'password',
				'description' => __( 'Token de Google con permisos https://www.googleapis.com/auth/business.manage.', 'convoca-publisher' ),
			),
			'convoca_publisher_gmb_refresh_token' => array(
				'title'       => __( 'Refresh token (recomendado)', 'convoca-publisher' ),
				'type'        => 'password',
				'description' => __( 'Con refresh token, client ID y client secret el canal pide un access token nuevo antes de cada envío: la conexión no caduca a la hora.', 'convoca-publisher' ),
			),
			'convoca_publisher_gmb_client_id'     => array(
				'title'       => __( 'Client ID de OAuth', 'convoca-publisher' ),
				'type'        => 'text',
				'description' => __( 'El de la aplicación de Google Cloud con la API de Business Profile habilitada.', 'convoca-publisher' ),
			),
			'convoca_publisher_gmb_client_secret' => array(
				'title'       => __( 'Client secret de OAuth', 'convoca-publisher' ),
				'type'        => 'password',
				'description' => __( 'Se guarda cifrado, igual que los tokens.', 'convoca-publisher' ),
			),
			'convoca_publisher_gmb_location'      => array(
				'title'       => __( 'Location ID', 'convoca-publisher' ),
				'type'        => 'text',
				'description' => __( 'Formato: accounts/{accountId}/locations/{locationId}', 'convoca-publisher' ),
			),
			'convoca_publisher_gmb_template'      => array(
				'title'       => __( 'Plantilla del post', 'convoca-publisher' ),
				'type'        => 'text',
				'description' => __( '{title}, {excerpt}, {url}. GMB limita a 1500 caracteres. Por defecto: {excerpt} — {url}', 'convoca-publisher' ),
			),
		);
	}

	public function validate_settings( array $settings ): array {
		$errors  = array();
		$token   = (string) ( $settings['convoca_publisher_gmb_token'] ?? '' );
		$refresh = (string) ( $settings['convoca_publisher_gmb_refresh_token'] ?? '' );
		$client  = (string) ( $settings['convoca_publisher_gmb_client_id'] ?? '' );
		$secreto = (string) ( $settings['convoca_publisher_gmb_client_secret'] ?? '' );

		if ( '' === $token && '' === $refresh ) {
			$errors[] = __( 'Hace falta el access token o el refresh token de Google.', 'convoca-publisher' );
		}

		if ( '' !== $refresh && ( '' === $client || '' === $secreto ) ) {
			$errors[] = __( 'Con refresh token hacen falta también el client ID y el client secret.', 'convoca-publisher' );
		}
		if ( empty( $settings['convoca_publisher_gmb_location'] ) ) {
			$errors[] = __( 'El Location ID de GMB es obligatorio.', 'convoca-publisher' );
		}
		return $errors;
	}
	public function verify_connection(): array {
		$location = $this->setting( 'convoca_publisher_gmb_location' );

		if ( '' === $location ) {
			return array(
				'success' => false,
				'message' => __( 'El Location ID de GMB no está configurado.', 'convoca-publisher' ),
			);
		}

		$token = $this->access_token();

		if ( '' !== $token['error'] ) {
			return array(
				'success' => false,
				'message' => $token['error'],
			);
		}

		$formato = self::validar_location( $location );

		if ( '' !== $formato ) {
			return array(
				'success' => false,
				'message' => $formato,
			);
		}

		if ( strlen( $token['token'] ) < 20 || strlen( $token['token'] ) > 4096 ) {
			return array(
				'success' => false,
				'message' => __( 'El token de Google no tiene un formato válido.', 'convoca-publisher' ),
			);
		}

		$comprobacion = self::comprobar_location( $location, $token['token'] );

		if ( '' !== $comprobacion['error'] ) {
			return array(
				'success' => false,
				'message' => $comprobacion['error'],
			);
		}

		return $this->resultado_verificacion( $comprobacion );
	}

	/**
	 * Traducir la respuesta de la comprobación a «conecta» o al motivo.
	 *
	 * @param array{code: int, body: array<string, mixed>|null, error: string} $comprobacion Respuesta.
	 * @return array{success: bool, message: string}
	 */
	private function resultado_verificacion( array $comprobacion ): array {
		if ( $comprobacion['code'] >= 200 && $comprobacion['code'] < 300 ) {
			return array(
				'success' => true,
				'message' => $this->mensaje_conexion(),
			);
		}

		if ( 401 === $comprobacion['code'] ) {
			return array(
				'success' => false,
				'message' => __( 'El token de Google ha caducado o no es válido. Configura el refresh token para que se renueve solo, o vuelve a conectar la cuenta en Ajustes.', 'convoca-publisher' ),
			);
		}

		return array(
			'success' => false,
			'message' => self::api_error( $comprobacion['body'], $comprobacion['code'] ),
		);
	}

	/**
	 * Formato del Location ID, o cadena vacía si vale.
	 *
	 * @param string $location Location ID.
	 */
	private static function validar_location( string $location ): string {
		if ( preg_match( '#^accounts/[^/]+/locations/[^/]+$#', $location ) ) {
			return '';
		}

		return __( 'El Location ID debe tener el formato: accounts/{accountId}/locations/{locationId}', 'convoca-publisher' );
	}

	/**
	 * Consulta ligera para saber si el token y la ubicación sirven.
	 *
	 * @param string $location Location ID.
	 * @param string $token    Access token.
	 * @return array{code: int, body: array<string, mixed>|null, error: string}
	 */
	private static function comprobar_location( string $location, string $token ): array {
		$respuesta = wp_remote_get(
			"https://mybusiness.googleapis.com/v4/{$location}/localPosts?pageSize=1",
			array(
				'headers' => array(
					'Authorization' => "Bearer {$token}",
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			return array(
				'code'  => 0,
				'body'  => null,
				'error' => __( 'Error de conexión: ', 'convoca-publisher' ) . $respuesta->get_error_message(),
			);
		}

		$cuerpo = json_decode( (string) wp_remote_retrieve_body( $respuesta ), true );

		return array(
			'code'  => (int) wp_remote_retrieve_response_code( $respuesta ),
			'body'  => is_array( $cuerpo ) ? $cuerpo : null,
			'error' => '',
		);
	}

	/**
	 * Mensaje de conexión correcta, según haya renovación o no.
	 */
	private function mensaje_conexion(): string {
		if ( '' !== $this->setting( 'convoca_publisher_gmb_refresh_token' ) ) {
			return __( '✅ Conexión correcta. Location ID válido y el token se renueva solo.', 'convoca-publisher' );
		}

		return __( '✅ Conexión correcta. Location ID válido y accesible. El token caduca en una hora: configura el refresh token para que no haya que renovarlo a mano.', 'convoca-publisher' );
	}
}
