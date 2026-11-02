<?php
/**
 * Los cuatro manejadores de la cola, sin `exit`.
 *
 * Vivían en `Admin` mezclados con la pantalla y terminaban en `wp_safe_redirect()` + `exit`, así que
 * ejecutarlos dentro de PHPUnit mataba la corrida. Aquí solo hay lógica: reciben datos ya saneados,
 * tocan la cola y **devuelven un resultado**. Quien redirige y sale es `Admin::terminar()`.
 *
 * Resultado: `array{ok: bool, aviso: string}`. Un `aviso` vacío significa «no hay nada que contar»,
 * que es distinto de un fallo: reprogramar con una fecha inválida no decía nada antes y sigue sin
 * decirlo.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manejadores de la cola.
 */
final class Manejador_Cola {

	/**
	 * Quitar un envío de la cola.
	 *
	 * @param string $ref Referencia del envío («schedule:12» o «retry:5»).
	 * @return array{ok: bool, aviso: string}
	 */
	public static function cancelar( string $ref ): array {
		if ( ! self::quitar( $ref ) ) {
			return array(
				'ok'    => false,
				'aviso' => __( 'No se ha podido quitar: ese envío ya no estaba en la cola.', 'convoca-publisher' ),
			);
		}

		return array(
			'ok'    => true,
			'aviso' => __( 'Envío quitado de la cola.', 'convoca-publisher' ),
		);
	}

	/**
	 * Reprogramar un envío.
	 *
	 * Con una fecha que no se puede leer no se toca nada y no se avisa: es lo que hacía antes.
	 *
	 * @param string $ref    Referencia del envío.
	 * @param string $dia    Día elegido en el calendario (a las 9:00 del sitio).
	 * @param string $cuando Fecha y hora completas de la lista.
	 * @return array{ok: bool, aviso: string}
	 */
	public static function reprogramar( string $ref, string $dia, string $cuando ): array {
		$sello = self::parse_when( $dia, $cuando );

		if ( $sello <= 0 ) {
			return array(
				'ok'    => false,
				'aviso' => '',
			);
		}

		if ( ! self::mover( $ref, $sello ) ) {
			return array(
				'ok'    => false,
				'aviso' => __( 'No se ha podido reprogramar: ese envío ya no estaba en la cola.', 'convoca-publisher' ),
			);
		}

		return array(
			'ok'    => true,
			'aviso' => __( 'Envío reprogramado.', 'convoca-publisher' ),
		);
	}

	/**
	 * Recolocar ahora mismo, sin esperar al cron.
	 *
	 * @return array{ok: bool, aviso: string}
	 */
	public static function recolocar(): array {
		$movidos = Queue::apply_spacing();

		return array(
			'ok'    => $movidos > 0,
			'aviso' => sprintf(
				/* translators: %d: envíos recolocados */
				_n( '%d envío recolocado.', '%d envíos recolocados.', $movidos, 'convoca-publisher' ),
				$movidos
			),
		);
	}

	/**
	 * Dar salida a mano a lo atrasado (una entrada o todas).
	 *
	 * @param bool $todos   Si se intentan todos los atrasados.
	 * @param int  $post_id Entrada concreta cuando no son todos.
	 * @return array{ok: bool, aviso: string}
	 */
	public static function forzar_atrasados( bool $todos, int $post_id ): array {
		$ids = $todos
			? array_map(
				static fn( array $fila ): int => (int) $fila['post_id'],
				array_merge(
					Queue::overdue(),
					array_map( static fn( int $id ): array => array( 'post_id' => $id ), Queue::needs_help() )
				)
			)
			: array( $post_id );

		$hechos = 0;

		foreach ( array_filter( $ids ) as $id ) {
			$publicador = Publisher::instance();

			if ( ! $publicador ) {
				continue;
			}

			$resultado = $publicador->publish_to_accounts( (int) $id, array(), true, true );
			$salio     = false;

			foreach ( $resultado as $cuenta => $envio ) {
				if ( '_' !== $cuenta[0] && ! empty( $envio['success'] ) ) {
					$salio = true;
				}
			}

			if ( $salio ) {
				Queue::forget_attempts( (int) $id );
				delete_post_meta( (int) $id, Queue::SCHEDULE_META );
				++$hechos;
				continue;
			}

			if ( Queue::count_attempt( (int) $id ) >= Queue::MAX_ATTEMPTS ) {
				Queue::give_up( (int) $id );
				Notifications::ask_for_help( (int) $id, Queue::MAX_ATTEMPTS );
			}
		}

		return array(
			'ok'    => $hechos > 0,
			'aviso' => sprintf(
				/* translators: %d: envíos que han salido */
				_n( '%d envío recuperado.', '%d envíos recuperados.', $hechos, 'convoca-publisher' ),
				$hechos
			),
		);
	}

	/**
	 * Del formulario a un sello de tiempo del sitio.
	 *
	 * Dos formas de decir cuándo: el día desde el que se suelta un envío en el calendario
	 * (`$dia`, a las 9:00, que es lo que se puede elegir arrastrando) o una fecha y hora
	 * completas (`$cuando`, de la lista de la cola). Todo en la zona horaria del sitio.
	 *
	 * @param string $dia    Día del calendario.
	 * @param string $cuando Fecha y hora completas.
	 * @return int Sello de tiempo, o 0 si no se entiende ninguna de las dos.
	 */
	public static function parse_when( string $dia, string $cuando ): int {
		$tz = wp_timezone();

		if ( '' !== $cuando ) {
			$fecha = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $cuando, $tz );

			return $fecha instanceof \DateTimeImmutable ? $fecha->getTimestamp() : 0;
		}

		if ( '' !== $dia ) {
			$fecha = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $dia . ' 09:00', $tz );

			return $fecha instanceof \DateTimeImmutable ? $fecha->getTimestamp() : 0;
		}

		return 0;
	}

	/**
	 * Referencia de un envío: tipo e identificador.
	 *
	 * @param string $ref «schedule:12» o «retry:5».
	 * @return array{0: string, 1: int} Tipo e identificador (0 si no hay).
	 */
	private static function partes( string $ref ): array {
		[$kind, $id] = array_pad( explode( ':', $ref, 2 ), 2, '' );

		return array( (string) $kind, (int) $id );
	}

	/**
	 * Mueve un envío a otra hora.
	 *
	 * @param string $ref  Referencia del envío.
	 * @param int    $when Sello de destino.
	 */
	private static function mover( string $ref, int $when ): bool {
		[$kind, $id] = self::partes( $ref );

		if ( $id <= 0 ) {
			return false;
		}

		return 'retry' === $kind ? Queue::reschedule_retry( $id, $when ) : Queue::reschedule_schedule( $id, $when );
	}

	/**
	 * Quita un envío de la cola.
	 *
	 * @param string $ref Referencia del envío.
	 */
	private static function quitar( string $ref ): bool {
		[$kind, $id] = self::partes( $ref );

		if ( $id <= 0 ) {
			return false;
		}

		return 'retry' === $kind ? Queue::cancel_retry( $id ) : Queue::cancel_schedule( $id );
	}
}
