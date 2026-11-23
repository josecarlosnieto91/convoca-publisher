<?php

/**
 * Modo de publicación y política por cuenta.
 *
 * @package    Convoca\Publisher
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace ConvocaPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Decide qué se hace con cada cuenta al publicar una entrada.
 *
 * Dos modos —`auto` (gratis) y `cola` (PRO)— y tres estados por cuenta dentro de `cola`:
 * `auto` envía, `revisar` deja una propuesta y `manual` no hace nada hasta que alguien comparte.
 *
 * Las reglas viven aquí, sin tocar WordPress más que para leer opciones, para poder probarlas a pelo:
 * el motor no vuelve a decidir por su cuenta a qué cuenta manda cada entrada (publisher#20).
 */
final class Policy {

	/**
	 * Opción con el modo de publicación (`auto` | `cola`).
	 */
	public const MODE_OPTION = 'convoca_publisher_publish_mode';

	/**
	 * Opción con el estado por cuenta.
	 */
	public const POLICY_OPTION = 'convoca_publisher_account_policy';

	/**
	 * Publica al guardar la entrada. Es el modo gratis.
	 */
	public const MODE_AUTO = 'auto';

	/**
	 * Cada cuenta decide: enviar, encolar o esperar a que se comparta a mano. Es PRO.
	 */
	public const MODE_QUEUE = 'cola';

	public const AUTO = 'auto';

	public const REVIEW = 'revisar';

	public const MANUAL = 'manual';

	/**
	 * Estados válidos por cuenta.
	 *
	 * @return string[]
	 */
	public static function states(): array {
		return array( self::AUTO, self::REVIEW, self::MANUAL );
	}

	/**
	 * Modo guardado, saneado.
	 */
	public static function mode(): string {
		$mode = (string) get_option( self::MODE_OPTION, self::MODE_AUTO );

		return self::MODE_QUEUE === $mode ? self::MODE_QUEUE : self::MODE_AUTO;
	}

	/**
	 * ¿Está disponible la cola de moderación (PRO)?
	 */
	public static function queue_available(): bool {
		return Plugin::tiene_licencia_publisher();
	}

	/**
	 * Mapa de estados guardado.
	 *
	 * @return array<string, string>
	 */
	public static function saved(): array {
		$mapa = get_option( self::POLICY_OPTION, array() );

		return is_array( $mapa ) ? $mapa : array();
	}

	/**
	 * Estado efectivo de una cuenta.
	 *
	 * - En modo `auto` todo publica, salvo lo que esté en `manual`. Un `revisar` guardado se trata
	 *   como `manual`: cambiar de modo no puede publicar sin revisión lo que estaba esperando.
	 * - Sin licencia PRO, `revisar` también cae a `manual` (nada sale solo por haber perdido la
	 *   licencia); `auto` sigue publicando, que es la función gratis.
	 *
	 * @param string $account_id Cuenta.
	 */
	public static function policy_for( string $account_id ): string {
		$mapa   = self::saved();
		$estado = isset( $mapa[ $account_id ] ) ? (string) $mapa[ $account_id ] : (string) ( $mapa['*'] ?? self::AUTO );

		if ( ! in_array( $estado, self::states(), true ) ) {
			$estado = self::AUTO;
		}

		if ( self::MODE_AUTO === self::mode() ) {
			return self::AUTO === $estado ? self::AUTO : self::MANUAL;
		}

		if ( ! self::queue_available() && self::AUTO !== $estado ) {
			return self::MANUAL;
		}

		return $estado;
	}

	/**
	 * Traduce las opciones viejas al modelo nuevo.
	 *
	 * Función pura: recibe lo guardado y devuelve lo que hay que escribir. Las cuatro
	 * combinaciones están fijadas en las pruebas, porque una migración mal mapeada publica lo que
	 * no debe o deja de publicar lo que sí.
	 *
	 * @param bool     $auto_publish        Opción vieja `convoca_publisher_auto_publish`.
	 * @param string   $moderation          Opción vieja `convoca_publisher_moderation`.
	 * @param string[] $moderation_channels Cuentas marcadas en la moderación por canal.
	 * @return array{mode: string, policy: array<string, string>}
	 */
	public static function migration( bool $auto_publish, string $moderation, array $moderation_channels ): array {
		if ( ! $auto_publish ) {
			// Nada se publicaba solo: se queda igual de prudente, sin llenar la cola de pendientes.
			return array(
				'mode'   => self::MODE_AUTO,
				'policy' => array( '*' => self::MANUAL ),
			);
		}

		if ( 'all' === $moderation ) {
			return array(
				'mode'   => self::MODE_QUEUE,
				'policy' => array( '*' => self::REVIEW ),
			);
		}

		if ( 'canal' === $moderation ) {
			$policy = array( '*' => self::AUTO );

			// Los ids de la opción vieja son ids de cuenta. Una cuenta que ya no exista no se
			// resucita: el mapa se queda con ella pero el motor solo recorre cuentas reales.
			foreach ( $moderation_channels as $account_id ) {
				$account_id = (string) $account_id;
				if ( '' !== $account_id ) {
					$policy[ $account_id ] = self::REVIEW;
				}
			}

			return array(
				'mode'   => self::MODE_QUEUE,
				'policy' => $policy,
			);
		}

		return array(
			'mode'   => self::MODE_AUTO,
			'policy' => array( '*' => self::AUTO ),
		);
	}
}
