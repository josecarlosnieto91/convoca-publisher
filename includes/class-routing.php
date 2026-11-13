<?php

/**
 * Enrutado de entradas a cuentas por categoría y etiqueta.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Decide qué cuentas reciben una entrada.
 *
 * Sin reglas guardadas se mantiene el comportamiento histórico: todas las cuentas.
 * Con reglas, solo las cuentas de las categorías/etiquetas que coinciden; una entrada
 * que no coincide con ninguna no se propone a nadie, que es lo que evita publicar
 * noticias del centro social en las cuentas de un proyecto.
 */
final class Routing {

	public const OPTION = 'convoca_publisher_routing';

	/**
	 * Reglas guardadas: clave `cat_12` o `tag_34` => ids de cuenta.
	 *
	 * @return array<string, string[]>
	 */
	public static function rules(): array {
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$rules = array();
		foreach ( $raw as $key => $accounts ) {
			if ( ! is_string( $key ) || ! preg_match( '/^(cat|tag)_[0-9]+$/', $key ) ) {
				continue;
			}

			$ids = array_values( array_filter( array_map( 'strval', (array) $accounts ) ) );
			if ( array() !== $ids ) {
				$rules[ $key ] = $ids;
			}
		}

		return $rules;
	}

	/**
	 * Claves de categoría y etiqueta de una entrada.
	 *
	 * @return string[]
	 */
	public static function post_keys( int $post_id ): array {
		$keys = array();

		foreach ( (array) wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) ) as $term_id ) {
			$keys[] = 'cat_' . (int) $term_id;
		}

		foreach ( (array) wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) ) as $term_id ) {
			$keys[] = 'tag_' . (int) $term_id;
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Filtra las cuentas que reciben la entrada.
	 *
	 * @param array<string, object> $accounts Cuentas disponibles.
	 * @return array<string, object>
	 */
	public static function filter_accounts( int $post_id, array $accounts ): array {
		$rules = self::rules();

		if ( array() === $rules ) {
			return $accounts;
		}

		$keys    = self::post_keys( $post_id );
		$allowed = array();

		foreach ( $rules as $key => $account_ids ) {
			if ( in_array( $key, $keys, true ) ) {
				$allowed = array_merge( $allowed, $account_ids );
			}
		}

		if ( array() === $allowed ) {
			return array();
		}

		return array_intersect_key( $accounts, array_flip( array_unique( $allowed ) ) );
	}

	/**
	 * Explicación para el editor cuando una entrada no va a ninguna cuenta.
	 */
	public static function no_match_note( int $post_id ): string {
		if ( array() === self::rules() ) {
			return '';
		}

		return __( 'Esta entrada no coincide con ninguna regla de categoría o etiqueta: no se propone a ninguna cuenta.', 'convoca-publisher' );
	}
}
