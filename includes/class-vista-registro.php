<?php
/**
 * El registro de publicaciones y la cola de moderación, aparte de `Admin`.
 *
 * Los dos pintaban sin llamar a nada de la clase: se leen datos y se escribe HTML. Sacarlos deja
 * `Admin` más cerca de ser un controlador.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registro y moderación.
 */
final class Vista_Registro {

	public static function log_page(): void {
		$logs = get_option( 'convoca_publisher_publish_log', array() );
		$logs = is_array( $logs ) ? $logs : array();

		$redes   = array();
		$nombres = array();

		foreach ( Plugin::accounts() as $id => $cuenta ) {
			$redes[ (string) $id ]   = $cuenta->get_channel_id();
			$nombres[ (string) $id ] = $cuenta->get_name();
		}

		// Solo se lee para filtrar la vista: no cambia nada, así que no hay nada que verificar.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
		$red    = isset( $_GET['cp-red'] ) ? sanitize_key( wp_unslash( (string) $_GET['cp-red'] ) ) : '';
		$cuenta = isset( $_GET['cp-cuenta'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['cp-cuenta'] ) ) : '';
		$estado = isset( $_GET['cp-estado'] ) ? sanitize_key( wp_unslash( (string) $_GET['cp-estado'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

		$datos = Admin\Log_View::facets( $logs, $redes );
		$vista = Admin\Log_View::filter(
			$logs,
			array(
				'network'  => $red,
				'account'  => $cuenta,
				'status'   => $estado,
				'networks' => $redes,
			)
		);

		// Se pagina de 25 en 25: el historial guarda hasta 200 filas y volcarlas todas de una vez
		// deja una pantalla larga de leer.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo se lee para paginar.
		$pagina   = isset( $_GET['cp-pagina'] ) ? max( 1, (int) $_GET['cp-pagina'] ) : 1;
		$paginado = Admin\Log_View::page( $vista, $pagina, 25 );

		$aviso = get_transient( 'convoca_publisher_queue_notice_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Historial de publicaciones', 'convoca-publisher' ); ?></h1>

			<?php if ( is_string( $aviso ) && '' !== $aviso ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $aviso ); ?></p></div>
				<?php delete_transient( 'convoca_publisher_queue_notice_' . get_current_user_id() ); ?>
			<?php endif; ?>

			<?php if ( 0 === $datos['total'] ) : ?>
				<p><?php echo esc_html__( 'No hay publicaciones registradas todavía.', 'convoca-publisher' ); ?></p>
			<?php else : ?>
				<form method="get" class="cp-filters">
					<input type="hidden" name="page" value="convoca-publisher-log" />
					<label for="cp-red"><?php echo esc_html__( 'Red', 'convoca-publisher' ); ?></label>
					<select name="cp-red" id="cp-red">
						<option value=""><?php echo esc_html__( 'Todas', 'convoca-publisher' ); ?></option>
						<?php foreach ( $datos['networks'] as $id_red ) : ?>
							<option value="<?php echo esc_attr( $id_red ); ?>" <?php selected( $red, $id_red ); ?>><?php echo esc_html( $id_red ); ?></option>
						<?php endforeach; ?>
					</select>

					<label for="cp-cuenta"><?php echo esc_html__( 'Cuenta', 'convoca-publisher' ); ?></label>
					<select name="cp-cuenta" id="cp-cuenta">
						<option value=""><?php echo esc_html__( 'Todas', 'convoca-publisher' ); ?></option>
						<?php foreach ( $datos['accounts'] as $id_cuenta => $etiqueta ) : ?>
							<option value="<?php echo esc_attr( $id_cuenta ); ?>" <?php selected( $cuenta, (string) $id_cuenta ); ?>><?php echo esc_html( Admin\Log_View::label( (string) $id_cuenta, $redes, $nombres ) ); ?></option>
						<?php endforeach; ?>
					</select>

					<label for="cp-estado"><?php echo esc_html__( 'Resultado', 'convoca-publisher' ); ?></label>
					<select name="cp-estado" id="cp-estado">
						<option value=""><?php echo esc_html__( 'Todos', 'convoca-publisher' ); ?></option>
						<option value="ok" <?php selected( $estado, 'ok' ); ?>><?php echo esc_html__( 'Salió', 'convoca-publisher' ); ?> (<?php echo (int) $datos['statuses']['ok']; ?>)</option>
						<option value="fail" <?php selected( $estado, 'fail' ); ?>><?php echo esc_html__( 'Falló', 'convoca-publisher' ); ?> (<?php echo (int) $datos['statuses']['fail']; ?>)</option>
					</select>

					<button type="submit" class="button"><?php echo esc_html__( 'Filtrar', 'convoca-publisher' ); ?></button>
					<?php if ( '' !== $red || '' !== $cuenta || '' !== $estado ) : ?>
						<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-publisher-log' ) ); ?>"><?php echo esc_html__( 'Quitar filtros', 'convoca-publisher' ); ?></a>
					<?php endif; ?>
				</form>

				<p class="cp-filters__count">
					<?php
					// Las cifras se calculan aparte y se castean en la llamada: la
					// comprobación de escapado de Plugin Check mira el argumento.
					$primera_fila = ( ( $paginado['page'] - 1 ) * 25 ) + 1;
					$ultima_fila  = ( ( $paginado['page'] - 1 ) * 25 ) + count( $paginado['entries'] );
					printf(
						/* translators: 1: primera fila que se ve, 2: última, 3: total, 4: página, 5: páginas. */
						esc_html__( 'Mostrando %1$d-%2$d de %3$d (página %4$d de %5$d).', 'convoca-publisher' ),
						(int) ( count( $paginado['entries'] ) > 0 ? $primera_fila : 0 ),
						(int) $ultima_fila,
						(int) $datos['total'],
						(int) $paginado['page'],
						(int) $paginado['pages']
					);
					?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=convoca_publisher_delete_log' ), 'convoca_publisher_delete_log' ) ); ?>" class="button" onclick="return confirm('<?php echo esc_js( __( '¿Borrar todo el historial?', 'convoca-publisher' ) ); ?>');">
						<?php echo esc_html__( 'Limpiar historial', 'convoca-publisher' ); ?>
					</a>
				</p>

				<?php if ( array() === $vista ) : ?>
					<p><?php echo esc_html__( 'Con esos filtros no hay nada.', 'convoca-publisher' ); ?></p>
				<?php else : ?>
					<div class="cp-log__scroll">
						<?php foreach ( $paginado['entries'] as $log ) : ?>
							<div class="cp-log__row">
								<span class="cp-status <?php echo ! empty( $log['success'] ) ? 'ok' : 'fail'; ?>">
									<?php echo ! empty( $log['success'] ) ? 'OK' : 'FAIL'; ?>
								</span>
								<span class="cp-log__time"><?php echo isset( $log['time'] ) ? esc_html( (string) $log['time'] ) : ''; ?></span>
								<span class="cp-log__channel"><?php echo esc_html( Admin\Log_View::label( (string) ( $log['channel'] ?? '' ), $redes, $nombres ) ); ?></span>
								<span class="cp-log__title"><?php echo isset( $log['title'] ) ? esc_html( (string) $log['title'] ) : ''; ?></span>
								<?php if ( ! empty( $log['test'] ) ) : ?>
									<span class="cp-log__test"><?php echo esc_html__( 'prueba', 'convoca-publisher' ); ?></span>
								<?php endif; ?>
								<span class="cp-log__id"><?php echo esc_html__( 'Entrada', 'convoca-publisher' ) . ' #' . (int) ( $log['post_id'] ?? 0 ); ?></span>
								<?php if ( Admin\Log_View::retryable( $log ) ) : ?>
									<a class="cp-log__retry button-link" href="
									<?php
									echo esc_url(
										wp_nonce_url(
											admin_url( 'admin.php?action=convoca_publisher_retry_log&post=' . (int) $log['post_id'] . '&channel=' . rawurlencode( (string) $log['channel'] ) ),
											'convoca_publisher_retry_log_' . (int) $log['post_id'] . '_' . (string) $log['channel']
										)
									);
									?>
									"><?php echo esc_html__( 'Reintentar', 'convoca-publisher' ); ?></a>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( $paginado['pages'] > 1 ) : ?>
						<p class="cp-paginacion">
							<?php
							// Los filtros se conservan al cambiar de página: perderlos al paginar es
							// de las cosas que más molesta.
							$enlace = static function ( int $destino ) use ( $red, $cuenta, $estado ): string {
								return add_query_arg(
									array_filter(
										array(
											'page'      => 'convoca-publisher-log',
											'cp-red'    => $red,
											'cp-cuenta' => $cuenta,
											'cp-estado' => $estado,
											'cp-pagina' => $destino,
										)
									),
									admin_url( 'admin.php' )
								);
							};
	?>
							<?php if ( $paginado['page'] > 1 ) : ?>
								<a class="button" href="<?php echo esc_url( $enlace( $paginado['page'] - 1 ) ); ?>">&laquo; <?php echo esc_html__( 'Anterior', 'convoca-publisher' ); ?></a>
							<?php endif; ?>
							<span class="cp-paginacion__actual">
								<?php
								printf(
								/* translators: 1: página actual, 2: total de páginas. */
									esc_html__( 'Página %1$d de %2$d', 'convoca-publisher' ),
									(int) $paginado['page'],
									(int) $paginado['pages']
								);
								?>
							</span>
							<?php if ( $paginado['page'] < $paginado['pages'] ) : ?>
								<a class="button" href="<?php echo esc_url( $enlace( $paginado['page'] + 1 ) ); ?>"><?php echo esc_html__( 'Siguiente', 'convoca-publisher' ); ?> &raquo;</a>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>

			<?php
			if ( class_exists( Retry::class ) ) :
				$stats = Retry::get_queue_stats();
				if ( $stats['pending'] > 0 || $stats['failed'] > 0 ) :
					?>
				<div class="cp-section">
					<h2><?php echo esc_html__( 'Cola de reintentos', 'convoca-publisher' ); ?></h2>
					<p><?php echo esc_html__( 'Pendientes: ', 'convoca-publisher' ) . intval( $stats['pending'] ); ?> |
					<?php echo esc_html__( 'Fallidos: ', 'convoca-publisher' ) . intval( $stats['failed'] ); ?></p>
				</div>
					<?php
			endif;
endif;
			?>
		</div>
		<?php
	}


	public static function moderacion(): void {
		/**
		 * Filas de la cola de revisión.
		 *
		 * @var array<int, object{id: int|string, post_id: int|string, channel: string, created_at?: string}> $items
		 */
		$items = Retry::get_review_items();

		$notice = get_transient( 'convoca_publisher_review_notice' );
		if ( false !== $notice ) {
			delete_transient( 'convoca_publisher_review_notice' );
			$class   = ! empty( $notice['success'] ) ? 'notice-success' : 'notice-error';
			$icon    = ! empty( $notice['success'] ) ? '✅' : '❌';
			$message = $notice['message'] ?? $notice['error'] ?? '';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $icon ) . ' ' . esc_html( $message ) . '</p></div>';
		}
		?>
		<div class="cp-section">
			<h2><?php echo esc_html__( 'Cola de moderación', 'convoca-publisher' ); ?></h2>
			<?php if ( empty( $items ) ) : ?>
				<p><?php echo esc_html__( 'No hay publicaciones pendientes de revisión.', 'convoca-publisher' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Entrada', 'convoca-publisher' ); ?></th>
							<th><?php echo esc_html__( 'Canal', 'convoca-publisher' ); ?></th>
							<th><?php echo esc_html__( 'Fecha', 'convoca-publisher' ); ?></th>
							<th><?php echo esc_html__( 'Acciones', 'convoca-publisher' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( $items as $item ) :
						$review_id   = (int) $item->id;
						$post        = get_post( (int) $item->post_id );
						$approve_url = wp_nonce_url( admin_url( 'admin-post.php?action=convoca_publisher_approve_review&id=' . $review_id ), 'convoca_publisher_review_' . $review_id );
						$reject_url  = wp_nonce_url( admin_url( 'admin-post.php?action=convoca_publisher_reject_review&id=' . $review_id ), 'convoca_publisher_review_' . $review_id );
						?>
						<tr>
							<td>
								<?php
								// Puede no haber enlace de edición: `get_edit_post_link()` devuelve null para
								// una entrada borrada y `esc_url( null )` deja un `PHP Deprecated` en el registro.
								$convoca_enlace = $post ? get_edit_post_link( (int) $item->post_id ) : '';
								if ( $post && $convoca_enlace ) :
									?>
									<a href="<?php echo esc_url( $convoca_enlace ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
								<?php elseif ( $post ) : ?>
									<?php echo esc_html( $post->post_title ); ?>
								<?php else : ?>
									<?php echo esc_html__( '(entrada eliminada)', 'convoca-publisher' ) . ' #' . intval( $item->post_id ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) $item->channel ); ?></td>
							<td><?php echo esc_html( (string) ( $item->created_at ?? '' ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( $approve_url ); ?>" class="button button-primary"><?php echo esc_html__( 'Aprobar', 'convoca-publisher' ); ?></a>
								<a href="<?php echo esc_url( $reject_url ); ?>" class="button"><?php echo esc_html__( 'Rechazar', 'convoca-publisher' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
