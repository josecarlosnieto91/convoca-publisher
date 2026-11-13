<?php

/**
 * Validación previa de una publicación para una cuenta.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Un único camino para lo que bloquea y lo que solo conviene avisar.
 *
 * Lo usan la propuesta, el editor y el envío. Si cada pantalla hiciera su propia
 * comprobación, la propuesta podría decir «todo bien» y la red rechazar la imagen
 * al enviar, que es justo lo que el issue 18 pide evitar.
 */
final class Preflight {

	/**
	 * Validar una entrada para una cuenta.
	 *
	 * @return array{
	 *   ok: bool,
	 *   blockers: string[],
	 *   warnings: string[],
	 *   message: string,
	 *   account_name: string,
	 *   network: string,
	 *   chars: int,
	 *   limit: int,
	 *   image: array{id: int, url: string, width: int, height: int, mime: string, ratio: float}
	 * }
	 */
	public static function validate( int $post_id, string $account_id, bool $check_url = true ): array {
		$post    = get_post( $post_id );
		$channel = convoca_publisher()->get_channel( $account_id );

		if ( ! $post instanceof \WP_Post || ! $channel ) {
			return self::empty_result();
		}

		$network = $channel instanceof Channel_Profile ? $channel->get_channel_id() : $channel->get_id();
		$is_ig   = 'instagram' === $network || self::publishes_to_instagram( $channel, $network );

		$message = Publisher::instance() ? Publisher::instance()->preview_message( $post_id, $account_id ) : '';
		$rules   = Platform_Rules::check( $is_ig ? 'instagram' : $network, $message );
		$image   = self::image( $post_id );

		$blockers = array();
		$warnings = array();

		if ( '' === trim( (string) $post->post_title ) ) {
			$blockers[] = __( 'La entrada no tiene título.', 'convoca-publisher' );
		}

		if ( '' === $image['url'] ) {
			$warnings[] = __( 'No hay imagen destacada; la publicación saldrá solo con texto.', 'convoca-publisher' );
		}

		if ( $is_ig ) {
			if ( '' === $image['url'] ) {
				$blockers[] = __( 'Instagram exige una imagen destacada: sin ella la publicación se saltaría.', 'convoca-publisher' );
			} elseif ( 'image/jpeg' !== $image['mime'] ) {
				$blockers[] = sprintf(
					/* translators: %s: MIME de la imagen */
					__( 'Instagram solo acepta JPEG; la imagen es %s. Cambia la imagen destacada o expórtala en JPEG.', 'convoca-publisher' ),
					'' !== $image['mime'] ? $image['mime'] : __( 'de tipo desconocido', 'convoca-publisher' )
				);
			}

			if ( $image['ratio'] > 0 && ( $image['ratio'] < 0.8 || $image['ratio'] > 1.91 ) ) {
				$warnings[] = __( 'La proporción de la imagen se sale de 4:5–1.91:1: Instagram la recortará.', 'convoca-publisher' );
			}

			if ( '' !== $image['url'] && $check_url ) {
				$status = self::url_status( $image['url'] );
				if ( 200 !== $status ) {
					$blockers[] = sprintf(
						/* translators: 1: código HTTP, 2: URL de la imagen */
						__( 'Instagram necesita una imagen pública que responda 200; %1$s devolvió %2$d.', 'convoca-publisher' ),
						$image['url'],
						$status
					);
				}
			}
		}

		if ( ! $rules['fit'] ) {
			$warnings[] = sprintf(
				/* translators: 1: red, 2: caracteres, 3: límite */
				__( 'El texto no cabe en %1$s: se recortará de %2$d a %3$d caracteres.', 'convoca-publisher' ),
				$is_ig ? 'Instagram' : $channel->get_name(),
				$rules['chars'],
				$rules['limit']
			);
		}

		foreach ( $rules['warnings'] as $warning ) {
			$warnings[] = $warning;
		}

		if ( 'instagram' === $network || self::publishes_to_instagram( $channel, $network ) ) {
			$warnings[] = __( 'Instagram no permite editar el pie ni borrar el medio después de publicar: revisa el texto antes de aprobar.', 'convoca-publisher' );
		}

		return array(
			'ok'           => array() === $blockers,
			'blockers'     => $blockers,
			'warnings'     => array_values( array_unique( $warnings ) ),
			'message'      => (string) $rules['message'],
			'account_name' => $channel->get_name(),
			'network'      => $is_ig ? 'instagram' : $network,
			'chars'        => (int) $rules['chars'],
			'limit'        => (int) $rules['limit'],
			'image'        => $image,
		);
	}

	/**
	 * ¿Esta cuenta de Facebook publica también en Instagram?
	 *
	 * El canal Facebook es el que lleva el ID de Instagram; se mira en los ajustes del
	 * perfil para que dos cuentas de Facebook puedan tener Instagram distinto.
	 *
	 * @param object $channel Cuenta o canal.
	 */
	private static function publishes_to_instagram( object $channel, string $network ): bool {
		if ( 'facebook' !== $network ) {
			return false;
		}

		if ( $channel instanceof Channel_Profile ) {
			$settings = $channel->get_settings();
			if ( ! empty( $settings['convoca_publisher_instagram_business_id'] ) ) {
				return true;
			}
		}

		return ! empty( get_option( 'convoca_publisher_instagram_business_id', '' ) );
	}

	/**
	 * Imagen destacada: URL, dimensiones y MIME.
	 *
	 * @return array{id: int, url: string, width: int, height: int, mime: string, ratio: float}
	 */
	private static function image( int $post_id ): array {
		$empty = array(
			'id'     => 0,
			'url'    => '',
			'width'  => 0,
			'height' => 0,
			'mime'   => '',
			'ratio'  => 0.0,
		);

		$id = (int) get_post_thumbnail_id( $post_id );
		if ( $id <= 0 ) {
			return $empty;
		}

		$url  = (string) wp_get_attachment_image_url( $id, 'full' );
		$meta = (array) wp_get_attachment_metadata( $id );

		$width  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

		return array(
			'id'     => $id,
			'url'    => $url,
			'width'  => $width,
			'height' => $height,
			'mime'   => (string) get_post_mime_type( $id ),
			'ratio'  => $height > 0 ? round( $width / $height, 3 ) : 0.0,
		);
	}

	/**
	 * Código HTTP de la imagen, cacheado por petición.
	 */
	private static function url_status( string $url ): int {
		static $cache = array();

		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}

		if ( ! function_exists( 'wp_remote_head' ) ) {
			return $cache[ $url ] = 200;
		}

		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 2,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $cache[ $url ] = 0;
		}

		return $cache[ $url ] = (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Resultado vacío cuando no hay entrada o cuenta.
	 *
	 * @return array{ok: bool, blockers: string[], warnings: string[], message: string, account_name: string, network: string, chars: int, limit: int, image: array{id: int, url: string, width: int, height: int, mime: string, ratio: float}}
	 */
	private static function empty_result(): array {
		return array(
			'ok'           => false,
			'blockers'     => array( __( 'La entrada o la cuenta no existen.', 'convoca-publisher' ) ),
			'warnings'     => array(),
			'message'      => '',
			'account_name' => '',
			'network'      => '',
			'chars'        => 0,
			'limit'        => 0,
			'image'        => array(
				'id'     => 0,
				'url'    => '',
				'width'  => 0,
				'height' => 0,
				'mime'   => '',
				'ratio'  => 0.0,
			),
		);
	}
}
