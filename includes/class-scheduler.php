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

/**
 * Scheduler — publish posts to social media at a scheduled time.
 */

namespace ConvocaPublisher;

defined( 'ABSPATH' ) || exit;

class Scheduler {

	public const CRON_HOOK = 'convoca_publisher_scheduled_publish';

	/** Gancho del recolocado por espaciado. */
	public const SPACING_HOOK = 'convoca_publisher_apply_spacing';

	/** Intentos por entrada y cuenta que lleva el reintento desde el historial. */
	private const RETRY_STATE_META = '_convoca_publisher_retry_failures';

	/** Marca de que ya se avisó del fallo definitivo. */
	private const RETRY_NOTIFIED_META = '_convoca_publisher_retry_notified';

	public static function init(): void {
		add_filter( 'cron_schedules', array( self::class, 'add_cron_interval' ) );
		add_action( 'convoca_publisher_retry_failed_posts', array( self::class, 'retry_failed' ) );
		add_action( self::CRON_HOOK, array( self::class, 'publish_scheduled' ) );
		// El espaciado se recalcula en cada vuelta: lo que caiga dentro del intervalo
		// se recoloca solo, sin que nadie tenga que entrar a la cola.
		add_action( self::SPACING_HOOK, array( Queue::class, 'apply_spacing' ) );

		if ( ! wp_next_scheduled( 'convoca_publisher_retry_failed_posts' ) ) {
			wp_schedule_event( time(), 'hourly', 'convoca_publisher_retry_failed_posts' );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'every_15min', self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::SPACING_HOOK ) ) {
			wp_schedule_event( time(), 'every_15min', self::SPACING_HOOK );
		}
	}

	/**
	 * Registra el intervalo de cinco minutos de la cola.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Programaciones.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public static function add_cron_interval( array $schedules ): array {
		$schedules['every_15min'] = array(
			'interval' => 900,
			'display'  => __( 'Cada 15 minutos', 'convoca-publisher' ),
		);
		return $schedules;
	}

	/**
	 * Publish posts whose scheduled time has arrived.
	 */
	public static function publish_scheduled(): void {
		// Espaciado: no se sueltan varias publicaciones seguidas. Si el último envío
		// programado fue hace menos que el intervalo, este turno no se publica nada;
		// los que estén dentro del intervalo se habrán recolocado solos (Queue).
		if ( ! Queue::can_publish_now() ) {
			return;
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = %s
                 AND meta_value <= %d
                 AND meta_value > 0",
				Queue::SCHEDULE_META,
				time()
			)
		);

		// El más antiguo de los que tocan, uno por vuelta: el resto esperan su turno.
		$elegido = null;

		foreach ( $rows as $row ) {
			$cuando = (int) $row->meta_value;

			if ( null === $elegido || $cuando < $elegido['time'] ) {
				$elegido = array(
					'post_id' => (int) $row->post_id,
					'time'    => $cuando,
				);
			}
		}

		if ( null === $elegido ) {
			return;
		}

		$post = get_post( $elegido['post_id'] );

		if ( ! $post || 'publish' !== $post->post_status ) {
			delete_post_meta( $elegido['post_id'], Queue::SCHEDULE_META );

			return;
		}

		$publisher = Publisher::instance();

		if ( ! $publisher ) {
			return;
		}

		$resultado  = $publisher->publish_post( $elegido['post_id'], true );
		$hubo_exito = false;

		foreach ( $resultado as $cuenta => $envio ) {
			if ( '_' !== $cuenta[0] && ! empty( $envio['success'] ) ) {
				$hubo_exito = true;
			}
		}

		if ( $hubo_exito ) {
			Queue::mark_published();
			Queue::forget_attempts( $elegido['post_id'] );
			delete_post_meta( $elegido['post_id'], Queue::SCHEDULE_META );

			return;
		}

		// No salió en ninguna cuenta. Antes se borraba la marca igual y el envío se perdía
		// en silencio; ahora se le dan unas vueltas más y, agotadas, se pide una mano.
		$intentos = Queue::count_attempt( $elegido['post_id'] );

		if ( $intentos >= Queue::MAX_ATTEMPTS ) {
			Queue::give_up( $elegido['post_id'] );
			Notifications::ask_for_help( $elegido['post_id'], $intentos );

			return;
		}

		Queue::reschedule_schedule( $elegido['post_id'], time() + Queue::interval() );
	}

	/**
	 * Cuentas de esta entrada cuyo último envío falló (por su id, no por su nombre).
	 *
	 * @return string[]
	 */
	private static function failed_accounts( int $post_id ): array {
		$resultados = get_post_meta( $post_id, '_convoca_publisher_publish_results', true );

		if ( ! is_array( $resultados ) ) {
			return array();
		}

		$fallidas = array();

		foreach ( $resultados as $cuenta => $envio ) {
			if ( '_' !== $cuenta[0] && empty( $envio['success'] ) ) {
				$fallidas[] = (string) $cuenta;
			}
		}

		return $fallidas;
	}

	public static function retry_failed(): void {
		$logs = get_option( 'convoca_publisher_publish_log', array() );

		if ( ! is_array( $logs ) ) {
			return;
		}

		foreach ( self::fallos_por_entrada( $logs ) as $post_id => $canales ) {
			$post = get_post( $post_id );

			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			$publisher = Publisher::instance();

			if ( ! $publisher ) {
				continue;
			}

			if ( Queue::attempts( $post_id ) >= Queue::MAX_ATTEMPTS ) {
				self::avisar_agotado( $post_id, Queue::MAX_ATTEMPTS );
				continue;
			}

			$estado  = self::estado_reintentos( $post_id );
			$debidas = self::canales_debidos( $post_id, $canales, $estado );

			if ( array() === $debidas ) {
				continue;
			}

			$publisher->publish_to_accounts( $post_id, $debidas, true );
			$estado = self::anotar_resultado( $post_id, $debidas, $estado );

			if ( array() === $estado ) {
				delete_post_meta( $post_id, self::RETRY_STATE_META );
			} else {
				update_post_meta( $post_id, self::RETRY_STATE_META, $estado );
			}
		}
	}

	/**
	 * Agrupa el historial de fallos por entrada y cuenta.
	 *
	 * @param array<int, array<string, mixed>> $logs Historial.
	 * @return array<int, array<string, bool>>
	 */
	private static function fallos_por_entrada( array $logs ): array {
		$fallos = array();

		foreach ( $logs as $log ) {
			if ( ! empty( $log['success'] ) || empty( $log['post_id'] ) ) {
				continue;
			}

			$fallos[ (int) $log['post_id'] ][ (string) ( $log['channel'] ?? '' ) ] = true;
		}

		return $fallos;
	}

	/**
	 * Contador de reintentos guardado para una entrada.
	 *
	 * @param int $post_id Entrada.
	 * @return array<string, array{attempts: int, next: int}>
	 */
	private static function estado_reintentos( int $post_id ): array {
		$estado = get_post_meta( $post_id, self::RETRY_STATE_META, true );

		return is_array( $estado ) ? $estado : array();
	}

	/**
	 * Cuentas que toca reintentar ahora: fallidas, con margen, fuera de backoff y sin cola propia.
	 *
	 * @param int                                  $post_id Entrada.
	 * @param array<string, bool>                  $canales Cuentas del historial.
	 * @param array<string, array{attempts: int, next: int}> $estado Contador.
	 * @return string[]
	 */
	private static function canales_debidos( int $post_id, array $canales, array $estado ): array {
		$fallidas   = self::failed_accounts( $post_id );
		$candidatas = array_values( array_intersect( array_keys( $canales ), $fallidas ) );

		if ( array() === $candidatas ) {
			$candidatas = $fallidas;
		}

		$debidas = array();
		$ahora   = time();

		foreach ( $candidatas as $canal ) {
			$canal   = (string) $canal;
			$intento = $estado[ $canal ] ?? array();
			$hechos  = (int) ( $intento['attempts'] ?? 0 );
			$cuando  = (int) ( $intento['next'] ?? 0 );

			if ( $hechos >= Retry::max_attempts() ) {
				self::avisar_agotado( $post_id, $hechos );
				continue;
			}

			// Si la cola ya lleva esta cuenta, manda la cola: un solo dueño del reintento.
			if ( $cuando > $ahora || Retry::has_pending( $post_id, $canal ) ) {
				continue;
			}

			$debidas[] = $canal;
		}

		return $debidas;
	}

	/**
	 * Apunta el resultado de los reintentos en el contador.
	 *
	 * @param int                                            $post_id Entrada.
	 * @param string[]                                       $debidas Cuentas reintentadas.
	 * @param array<string, array{attempts: int, next: int}> $estado  Contador.
	 * @return array<string, array{attempts: int, next: int}>
	 */
	private static function anotar_resultado( int $post_id, array $debidas, array $estado ): array {
		$resultados = get_post_meta( $post_id, '_convoca_publisher_publish_results', true );
		$resultados = is_array( $resultados ) ? $resultados : array();

		foreach ( $debidas as $canal ) {
			if ( ! empty( $resultados[ $canal ]['success'] ) ) {
				unset( $estado[ $canal ] );
				continue;
			}

			$hechos = (int) ( $estado[ $canal ]['attempts'] ?? 0 ) + 1;

			$estado[ $canal ] = array(
				'attempts' => $hechos,
				'next'     => Retry::get_next_attempt_time( $hechos ),
			);

			if ( $hechos >= Retry::max_attempts() ) {
				self::avisar_agotado( $post_id, $hechos );
			}
		}

		return $estado;
	}

	/**
	 * Avisa una sola vez de que una entrada ha agotado los reintentos.
	 *
	 * @param int $post_id  Entrada.
	 * @param int $intentos Intentos acumulados.
	 */
	private static function avisar_agotado( int $post_id, int $intentos ): void {
		if ( '1' === (string) get_post_meta( $post_id, self::RETRY_NOTIFIED_META, true ) ) {
			return;
		}

		update_post_meta( $post_id, self::RETRY_NOTIFIED_META, '1' );
		Notifications::ask_for_help( $post_id, $intentos );
	}
}
