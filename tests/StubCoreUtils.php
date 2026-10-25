<?php
/**
 * Doble de `Convoca\Core\Utils` (clase del NÚCLEO, no de este plugin).
 *
 * El arranque de Publisher y varias de sus clases usan utilidades del núcleo. En el CI el workflow clona
 * `convoca-core` al lado; en local puede no estar, así que el arnés aporta el contrato mínimo que se usa aquí.
 *
 * @package ConvocaPublisher\Tests
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\Core\Utils' ) ) {

	/**
	 * Utilidades del núcleo (doble).
	 */
	class Utils {

		/**
		 * Limitador de intentos: la prueba decide si deja pasar.
		 *
		 * @param string $accion    Acción.
		 * @param int    $max       Máximo.
		 * @param int    $ventana   Ventana en segundos.
		 * @param string $identidad Identidad.
		 * @return bool
		 */
		public static function check_rate_limit( $accion, $max = 10, $ventana = 300, $identidad = '' ): bool {
			unset( $accion, $max, $ventana, $identidad );
			return (bool) ( $GLOBALS['_cp_test_rate_ok'] ?? true );
		}
	}
}
