<?php
/**
 * La pestaña de la cola de publicación.
 *
 * Calendario (mes y semana), lista de lo que espera turno y lo atascado. Eran doce métodos
 * privados dentro de `Admin`; salen aquí enteros, sin cambiar una etiqueta: la prueba de que no
 * se ha movido nada es que el HTML de la pestaña sale idéntico byte a byte.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vista de la cola.
 */
final class Vista_Cola {

	/**
	 * La pestaña de la cola: el calendario, la lista y lo atascado.
	 */
	public static function tab(): void {
		$aviso = get_transient( 'convoca_publisher_queue_notice_' . get_current_user_id() );

		if ( false !== $aviso ) {
			delete_transient( 'convoca_publisher_queue_notice_' . get_current_user_id() );
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( (string) $aviso ); ?></p></div>
			<?php
		}

		$vista = isset( $_GET['vista'] ) ? sanitize_key( wp_unslash( (string) $_GET['vista'] ) ) : 'mes';
		$hoy   = new \DateTimeImmutable( 'now', wp_timezone() );
		$mes   = isset( $_GET['mes'] ) ? max( 1, min( 12, (int) $_GET['mes'] ) ) : (int) $hoy->format( 'n' );
		$anio  = isset( $_GET['anio'] ) ? max( 2020, min( 2100, (int) $_GET['anio'] ) ) : (int) $hoy->format( 'Y' );
		$dia   = isset( $_GET['dia'] ) ? max( 1, min( 31, (int) $_GET['dia'] ) ) : (int) $hoy->format( 'j' );
		?>
		<p class="description">
			<?php echo esc_html__( 'Una entrada puede salir en varias cuentas: cada una es un envío. Arrastra un envío a otro día para reprogramarlo, o hazlo desde la lista de abajo.', 'convoca-publisher' ); ?>
		</p>

		<div class="cp-cal__barra">
			<a class="button" href="<?php echo esc_url( self::queue_url( $vista, $anio, $mes, $dia, -1 ) ); ?>">&larr;</a>
			<strong class="cp-cal__titulo">
				<?php echo esc_html( 'semana' === $vista ? __( 'Semana del ', 'convoca-publisher' ) . self::queue_week_start( $anio, $mes, $dia )->format( 'd/m/Y' ) : self::queue_month_name( $anio, $mes ) ); ?>
			</strong>
			<a class="button" href="<?php echo esc_url( self::queue_url( $vista, $anio, $mes, $dia, 1 ) ); ?>">&rarr;</a>
			<a class="button" href="
			<?php
			echo esc_url(
				Vista_Canales::tab_url(
					'queue',
					array(
						'vista' => 'mes',
						'anio'  => (int) $hoy->format( 'Y' ),
						'mes'   => (int) $hoy->format( 'n' ),
						'dia'   => (int) $hoy->format( 'j' ),
					)
				)
			);
			?>
									"><?php echo esc_html__( 'Hoy', 'convoca-publisher' ); ?></a>
			<span class="cp-cal__vistas">
				<a class="button <?php echo 'mes' === $vista ? 'button-primary' : ''; ?>" href="
				<?php
				echo esc_url(
					Vista_Canales::tab_url(
						'queue',
						array(
							'vista' => 'mes',
							'anio'  => $anio,
							'mes'   => $mes,
							'dia'   => $dia,
						)
					)
				);
				?>
									"><?php echo esc_html__( 'Mes', 'convoca-publisher' ); ?></a>
				<a class="button <?php echo 'semana' === $vista ? 'button-primary' : ''; ?>" href="
				<?php
				echo esc_url(
					Vista_Canales::tab_url(
						'queue',
						array(
							'vista' => 'semana',
							'anio'  => $anio,
							'mes'   => $mes,
							'dia'   => $dia,
						)
					)
				);
				?>
									"><?php echo esc_html__( 'Semana', 'convoca-publisher' ); ?></a>
			</span>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cp-cal__recolocar">
				<input type="hidden" name="action" value="convoca_publisher_queue_spacing" />
				<?php wp_nonce_field( 'convoca_publisher_queue_spacing' ); ?>
				<button type="submit" class="button"><?php echo esc_html__( 'Recolocar ahora', 'convoca-publisher' ); ?></button>
			</form>
		</div>

		<form id="cp-cal-form" class="cp-hidden" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="convoca_publisher_queue_reschedule" />
			<input type="hidden" name="convoca_publisher_envio" value="" />
			<input type="hidden" name="convoca_publisher_dia" value="" />
			<?php wp_nonce_field( 'convoca_publisher_queue_reschedule' ); ?>
		</form>

		<?php
		if ( 'semana' === $vista ) {
			self::render_queue_week( $anio, $mes, $dia );
		} else {
			self::render_queue_month( $anio, $mes );
		}

		self::render_queue_stuck();
		self::render_queue_list();
	}

	/**
	 * Pinta la tabla de la cola de publicacion.
	 *
	 * @param array<int, array<string, mixed>> $envios
	 */
	private static function render_queue_table( array $envios, bool $atascados ): void {
		if ( array() === $envios ) {
			?>
			<p class="description"><?php echo esc_html__( 'No hay nada en la cola.', 'convoca-publisher' ); ?></p>
			<?php

			return;
		}
		?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Cuándo', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Cuenta', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Entrada', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Reprogramar', 'convoca-publisher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $envios as $envio ) : ?>
					<?php
					$kind = (string) ( $envio['kind'] ?? 'schedule' );
					$ref  = 'retry' === $kind ? 'retry:' . (int) ( $envio['id'] ?? 0 ) : 'schedule:' . (int) ( $envio['post_id'] ?? 0 );
					?>
					<tr>
						<td>
							<?php echo esc_html( (string) wp_date( 'd/m/Y H:i', (int) ( $envio['time'] ?? 0 ) ) ); ?>
							<?php if ( $atascados ) : ?>
								<div class="description"><?php echo esc_html( (string) ( $envio['error'] ?? '' ) ); ?></div>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ( $envio['account_name'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $envio['title'] ?? '' ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cp-acciones">
								<input type="hidden" name="action" value="convoca_publisher_queue_reschedule" />
								<input type="hidden" name="convoca_publisher_envio" value="<?php echo esc_attr( $ref ); ?>" />
								<?php wp_nonce_field( 'convoca_publisher_queue_reschedule' ); ?>
								<input type="datetime-local" name="convoca_publisher_cuando" value="<?php echo esc_attr( (string) wp_date( 'Y-m-d\TH:i', (int) ( $envio['time'] ?? 0 ) ) ); ?>" />
								<button type="submit" class="button"><?php echo esc_html__( 'Mover', 'convoca-publisher' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cp-acciones">
								<input type="hidden" name="action" value="convoca_publisher_queue_cancel" />
								<input type="hidden" name="convoca_publisher_envio" value="<?php echo esc_attr( $ref ); ?>" />
								<?php wp_nonce_field( 'convoca_publisher_queue_cancel' ); ?>
								<button type="submit" class="button" data-cp-confirm="<?php echo esc_attr__( '¿Quitar este envío de la cola?', 'convoca-publisher' ); ?>"><?php echo esc_html__( 'Quitar', 'convoca-publisher' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_queue_list(): void {
		$ahora    = time();
		$proximos = array_merge( Queue::scheduled_entries( $ahora - DAY_IN_SECONDS, $ahora + ( 90 * DAY_IN_SECONDS ) ), Queue::retry_entries() );
		$futuros  = array_values( array_filter( $proximos, static fn( array $e ): bool => (int) ( $e['time'] ?? 0 ) >= $ahora ) );
		$fallidos = array_values( array_filter( $proximos, static fn( array $e ): bool => (int) ( $e['time'] ?? 0 ) < $ahora ) );

		usort( $futuros, static fn( array $a, array $b ): int => ( $a['time'] ?? 0 ) <=> ( $b['time'] ?? 0 ) );
		usort( $fallidos, static fn( array $a, array $b ): int => ( $b['time'] ?? 0 ) <=> ( $a['time'] ?? 0 ) );
		?>
		<h2><?php echo esc_html__( 'Lo que espera turno', 'convoca-publisher' ); ?></h2>
		<?php self::render_queue_table( $futuros, false ); ?>

		<?php if ( array() !== $fallidos ) : ?>
			<h2><?php echo esc_html__( 'Atascado: toca revisarlo', 'convoca-publisher' ); ?></h2>
			<?php self::render_queue_table( $fallidos, true ); ?>
		<?php endif; ?>

		<h2><?php echo esc_html__( 'Lo último que salió', 'convoca-publisher' ); ?></h2>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Cuándo', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Cuenta', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Entrada', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Resultado', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Enlace', 'convoca-publisher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_slice( Queue::sent_entries( 20 ), 0, 20 ) as $salio ) : ?>
					<tr>
						<td><?php echo esc_html( $salio['time'] ); ?></td>
						<td><?php echo esc_html( $salio['account'] ); ?></td>
						<td><?php echo esc_html( $salio['title'] ); ?></td>
						<td>
							<?php if ( $salio['success'] ) : ?>
								<span class="cp-status cp-status--ok">✅ <?php echo esc_html__( 'Enviado', 'convoca-publisher' ); ?></span>
							<?php else : ?>
								<span class="cp-status cp-status--fail">❌ <?php echo esc_html( (string) $salio['detail'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $salio['permalink'] ) ) : ?>
								<a href="<?php echo esc_url( (string) $salio['permalink'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Ver entrada', 'convoca-publisher' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * La lista de la cola: lo que espera turno, lo que falló y lo último que salió.
	 */
	/**
	 * Lo que se quedó atrás: ni salió ni está esperando turno. Aquí es donde se ve lo que el
	 * cron no llegó a intentar, y donde se le puede dar salida a mano.
	 */
	private static function render_queue_stuck(): void {
		$atrasados = Queue::overdue();
		$ayuda     = Queue::needs_help();

		if ( array() === $atrasados && array() === $ayuda ) {
			return;
		}
		?>
		<h2><?php echo esc_html__( 'Se quedó atrás', 'convoca-publisher' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Programados cuya hora ya pasó y no han salido: el cron puede no haber llegado a intentarlo. Se les puede dar salida ahora.', 'convoca-publisher' ); ?>
		</p>
		<?php if ( array() !== $atrasados ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cp-acciones">
				<input type="hidden" name="action" value="convoca_publisher_queue_send_stuck" />
				<input type="hidden" name="convoca_publisher_todos" value="1" />
				<?php wp_nonce_field( 'convoca_publisher_queue_send_stuck' ); ?>
				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Enviar todo lo atrasado', 'convoca-publisher' ); ?></button>
			</form>
		<?php endif; ?>
		<?php
		self::render_stuck_table( $atrasados, (string) __( 'Atrasado', 'convoca-publisher' ) );

		if ( array() !== $ayuda ) {
			$filas = array();

			foreach ( $ayuda as $post_id ) {
				$filas[] = array(
					'post_id'      => $post_id,
					'time'         => (int) get_post_meta( $post_id, Queue::HELP_META, true ),
					'title'        => get_the_title( $post_id ),
					'account_name' => '',
					'attempts'     => Queue::attempts( $post_id ),
				);
			}

			echo '<h2>' . esc_html__( 'Ya no se insiste solo', 'convoca-publisher' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'Se intentó varias veces sin éxito. Se avisó por correo; desde aquí se puede reintentar cuando esté resuelto.', 'convoca-publisher' ) . '</p>';
			self::render_stuck_table( $filas, (string) __( 'Se dejó de insistir', 'convoca-publisher' ) );
		}
	}

	/**
	 * Rejilla del calendario (la usan la vista de mes y la de semana).
	 *
	 * @param array<int, \DateTimeImmutable> $dias
	 * @param array<string, array<int, array<string, mixed>>> $porDia
	 */
	private static function render_queue_grid( array $dias, array $porDia ): void {
		$hoy = (string) wp_date( 'Y-m-d' );
		?>
		<table class="cp-cal">
			<thead>
				<tr>
					<?php foreach ( $dias as $indice => $dia ) : ?>
						<?php if ( $indice < 7 ) : ?>
							<th scope="col"><?php echo esc_html( (string) wp_date( 'D', $dia->getTimestamp() ) ); ?></th>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_chunk( $dias, 7 ) as $semana ) : ?>
					<tr>
						<?php foreach ( $semana as $dia ) : ?>
							<?php
							$fecha = $dia->format( 'Y-m-d' );
							$clase = 'cp-cal__dia';

							if ( $fecha === $hoy ) {
								$clase .= ' cp-cal__hoy';
							}
							?>
							<td class="<?php echo esc_attr( $clase ); ?>" data-convoca-publisher-dia="<?php echo esc_attr( $fecha ); ?>">
								<div class="cp-cal__day-number"><?php echo esc_html( $dia->format( 'j' ) ); ?></div>
								<?php foreach ( $porDia[ $fecha ] ?? array() as $envio ) : ?>
									<?php echo self::queue_entry_html( $envio ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>
								<?php endforeach; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Envíos de un rango de días, agrupados por día natural del sitio.
	 *
	 * @return array<string, array<int, array<string, mixed>>> array<int, array<string, mixed>>>
	 */
	private static function queue_by_day( int $from, int $to ): array {
		$entradas = Queue::scheduled_entries( $from, $to );

		foreach ( Queue::retry_entries() as $reintento ) {
			if ( ( $reintento['time'] ?? 0 ) >= $from && ( $reintento['time'] ?? 0 ) <= $to ) {
				$entradas[] = $reintento;
			}
		}

		foreach ( Queue::sent_entries() as $salio ) {
			$cuando = (int) strtotime( $salio['time'] );

			if ( $cuando >= $from && $cuando <= $to ) {
				$entradas[] = array(
					'kind'         => 'sent',
					'time'         => $cuando,
					'title'        => $salio['title'],
					'account_name' => $salio['account'],
					'success'      => $salio['success'],
				);
			}
		}

		$porDia = array();

		foreach ( $entradas as $entrada ) {
			$dia                                    = wp_date( 'Y-m-d', (int) $entrada['time'] );
			$porDia[ false === $dia ? '' : $dia ][] = $entrada;
		}

		return $porDia;
	}

	/**
	 * Un envío dentro del calendario (movible si todavía no ha salido).
	 *
	 * @param array<string, mixed> $envio
	 */
	private static function queue_entry_html( array $envio ): string {
		$kind  = (string) ( $envio['kind'] ?? 'schedule' );
		$clase = 'cp-cal__envio';
		$ref   = '';
		$pista = '';

		if ( 'sent' === $kind ) {
			$clase .= ! empty( $envio['success'] ) ? ' cp-cal__envio--hecho' : ' cp-cal__envio--fallo';
		} elseif ( 'retry' === $kind && 'failed' === ( $envio['status'] ?? '' ) ) {
			$clase .= ' cp-cal__envio--fallo';
			$ref    = 'retry:' . (int) ( $envio['id'] ?? 0 );
			$pista  = (string) ( $envio['error'] ?? '' );
		} elseif ( 'retry' === $kind ) {
				$ref = 'retry:' . (int) ( $envio['id'] ?? 0 );
		} else {
			$ref = 'schedule:' . (int) ( $envio['post_id'] ?? 0 );
		}

		$texto = sprintf(
			'%s · %s — %s',
			wp_date( 'H:i', (int) ( $envio['time'] ?? 0 ) ),
			(string) ( $envio['account_name'] ?? '' ),
			(string) ( $envio['title'] ?? '' )
		);

		return sprintf(
			'<span class="%1$s"%2$s draggable="%3$s" title="%4$s">%5$s</span>',
			esc_attr( $clase ),
			'' !== $ref ? ' data-convoca-publisher-envio="' . esc_attr( $ref ) . '"' : '',
			'' !== $ref ? 'true' : 'false',
			esc_attr( trim( $texto . ( '' !== $pista ? ' — ' . $pista : '' ) ) ),
			esc_html( $texto )
		);
	}

	/**
	 * Enlace del calendario (mes anterior o siguiente, según el paso).
	 */
	private static function queue_url( string $vista, int $year, int $month, int $day, int $paso ): string {
		if ( 'semana' === $vista ) {
			$ref = self::queue_week_start( $year, $month, $day )->modify( $paso > 0 ? '+7 days' : '-7 days' );
		} else {
			$ref = ( new \DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), wp_timezone() ) )
				->modify( $paso > 0 ? '+1 month' : '-1 month' );
		}

		return Vista_Canales::tab_url(
			'queue',
			array(
				'vista' => $vista,
				'anio'  => (int) $ref->format( 'Y' ),
				'mes'   => (int) $ref->format( 'n' ),
				'dia'   => (int) $ref->format( 'j' ),
			)
		);
	}

	private static function render_queue_week( int $year, int $month, int $day ): void {
		$start = self::queue_week_start( $year, $month, $day );

		$dias = array();

		for ( $i = 0; $i < 7; ++$i ) {
			$dias[] = $start->modify( '+' . $i . ' days' );
		}

		$porDia = self::queue_by_day( $start->getTimestamp(), $start->modify( '+7 days' )->getTimestamp() );

		self::render_queue_grid( $dias, $porDia );
	}

	private static function render_queue_month( int $year, int $month ): void {
		$tz    = wp_timezone();
		$start = new \DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), $tz );
		$start = $start->modify( '-' . ( (int) $start->format( 'N' ) - 1 ) . ' days' );

		$dias = array();

		for ( $i = 0; $i < 42; ++$i ) {
			$dias[] = $start->modify( '+' . $i . ' days' );
		}

		$porDia = self::queue_by_day( $start->getTimestamp(), $start->modify( '+42 days' )->getTimestamp() );

		self::render_queue_grid( $dias, $porDia );
	}

	private static function queue_month_name( int $year, int $month ): string {
		$fecha = new \DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), wp_timezone() );
		$mes   = wp_date( 'F Y', $fecha->getTimestamp() );

		return false === $mes ? gmdate( 'F Y', $fecha->getTimestamp() ) : $mes;
	}

	/**
	 * El lunes de la semana a la que pertenece un día (el día manda: sin él, la vista de
	 * semana no podría avanzar, siempre caería en la semana del día que se le pase).
	 */
	private static function queue_week_start( int $year, int $month, int $day ): \DateTimeImmutable {
		$ref = new \DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, max( 1, min( 31, $day ) ) ), wp_timezone() );

		return $ref->modify( '-' . ( (int) $ref->format( 'N' ) - 1 ) . ' days' )->setTime( 0, 0 );
	}

	/**
	 * Pinta la tabla de envios que se dejaron de reintentar.
	 *
	 * @param array<int, array<string, mixed>> $filas
	 */
	private static function render_stuck_table( array $filas, string $estado ): void {
		?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Cuándo', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Entrada', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Intentos', 'convoca-publisher' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Qué hacer', 'convoca-publisher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $filas as $fila ) : ?>
					<tr>
						<td><?php echo esc_html( (string) wp_date( 'd/m/Y H:i', (int) $fila['time'] ) ); ?></td>
						<td><?php echo esc_html( (string) $fila['title'] ); ?></td>
						<td><?php echo esc_html( sprintf( '%d / %d', (int) ( $fila['attempts'] ?? 0 ), Queue::MAX_ATTEMPTS ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cp-acciones">
								<input type="hidden" name="action" value="convoca_publisher_queue_send_stuck" />
								<input type="hidden" name="convoca_publisher_post" value="<?php echo esc_attr( (string) $fila['post_id'] ); ?>" />
								<?php wp_nonce_field( 'convoca_publisher_queue_send_stuck' ); ?>
								<button type="submit" class="button"><?php echo esc_html__( 'Enviar ahora', 'convoca-publisher' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
