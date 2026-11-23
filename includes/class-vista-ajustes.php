<?php
/**
 * La pestaña de ajustes, aparte.
 *
 * Solo la vista: `register_settings()` y los saneadores se quedan en `Admin`, porque son el
 * contrato con WordPress (qué opción, qué callback) y moverlos cambiaría dónde engancha el
 * sitio con sus propios datos guardados.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes.
 */
final class Vista_Ajustes {
	public static function render(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Solo se usa para decidir si se muestra el aviso de «guardado»; el nonce ya lo comprobó options.php.
		if ( isset( $_POST['submit'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cambios guardados.', 'convoca-publisher' ) . '</p></div>';
		}
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'convoca_publisher_settings' ); ?>

			<!-- Aviso de privacidad -->
			<div class="cp-notice cp-notice--warn">
				<h3><?php echo esc_html__( '🔐 Aviso de privacidad', 'convoca-publisher' ); ?></h3>
				<p><?php echo esc_html__( 'Este plugin envía el título, extracto, URL, imagen destacada y etiquetas de tus entradas a APIs de terceros (Meta, LinkedIn, Twitter/X, TikTok, Google, Telegram, Mastodon). Los tokens de acceso se almacenan cifrados en la base de datos de WordPress (AES-256-GCM).', 'convoca-publisher' ); ?></p>
				<p>
					<label>
						<input type="hidden" name="convoca_publisher_privacy_acknowledged" value="0" />
						<input type="checkbox" name="convoca_publisher_privacy_acknowledged" value="1" <?php checked( get_option( 'convoca_publisher_privacy_acknowledged', false ) ); ?> />
						<?php echo esc_html__( 'He leído y acepto este aviso', 'convoca-publisher' ); ?>
					</label>
				</p>
			</div>

			<div class="cp-section">
				<h2><?php echo esc_html__( 'Configuración general', 'convoca-publisher' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="convoca_publisher_publish_mode"><?php echo esc_html__( 'Modo de publicación', 'convoca-publisher' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( Policy::MODE_OPTION ); ?>" id="convoca_publisher_publish_mode">
								<option value="<?php echo esc_attr( Policy::MODE_AUTO ); ?>" <?php selected( Policy::mode(), Policy::MODE_AUTO ); ?>><?php echo esc_html__( 'Automático — al publicar, sale en las cuentas configuradas (gratis)', 'convoca-publisher' ); ?></option>
								<option value="<?php echo esc_attr( Policy::MODE_QUEUE ); ?>" <?php selected( Policy::mode(), Policy::MODE_QUEUE ); ?> <?php disabled( ! Policy::queue_available() ); ?>><?php echo esc_html__( 'Cola de moderación — cada cuenta decide: enviar, revisar o esperar (PRO)', 'convoca-publisher' ); ?></option>
							</select>
							<p class="description">
								<?php
								echo esc_html(
									Policy::queue_available()
										? __( 'En modo cola, una cuenta en «revisar» deja una propuesta para aprobar y una en «manual» no envía nada hasta que la compartas a mano.', 'convoca-publisher' )
										: __( 'La cola de moderación necesita licencia PRO. Mientras no la haya, las cuentas en «revisar» se tratan como «manual»: nada sale solo.', 'convoca-publisher' )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Avisos por correo', 'convoca-publisher' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="convoca_publisher_email_alerts" value="1" <?php checked( get_option( 'convoca_publisher_email_alerts', '1' ), '1' ); ?> />
								<?php echo esc_html__( 'Escribir a quien administra el sitio cuando un envío no salga tras varios intentos', 'convoca-publisher' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'En pantalla el aviso solo lo ve quien entra a mirar; el correo llega aunque nadie entre.', 'convoca-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="convoca_publisher_queue_interval"><?php echo esc_html__( 'Espaciado entre envíos', 'convoca-publisher' ); ?></label></th>
						<td>
							<select name="convoca_publisher_queue_interval" id="convoca_publisher_queue_interval">
								<?php
								foreach ( array(
									0    => __( 'Sin espaciado', 'convoca-publisher' ),
									900  => __( '15 minutos', 'convoca-publisher' ),
									1800 => __( '30 minutos', 'convoca-publisher' ),
									3600 => __( '1 hora', 'convoca-publisher' ),
									7200 => __( '2 horas', 'convoca-publisher' ),
								) as $segundos => $etiqueta ) :
									?>
									<option value="<?php echo esc_attr( (string) $segundos ); ?>" <?php selected( Queue::interval(), $segundos ); ?>><?php echo esc_html( $etiqueta ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html__( 'Cuánto tiene que pasar, como mínimo, entre dos publicaciones, para no soltar varias de golpe. Lo que caiga dentro se recoloca solo.', 'convoca-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="convoca_publisher_daily_limit"><?php echo esc_html__( 'Límite diario por cuenta', 'convoca-publisher' ); ?></label></th>
						<td>
							<input type="number" min="0" max="20" step="1" name="convoca_publisher_daily_limit" id="convoca_publisher_daily_limit" value="<?php echo esc_attr( (string) Queue::daily_limit() ); ?>" class="small-text" />
							<p class="description"><?php echo esc_html__( 'Máximo de publicaciones correctas por cuenta y día. 0 = sin límite. Evita ráfagas que el algoritmo penaliza.', 'convoca-publisher' ); ?></p>
						</td>
					</tr>
					<?php self::render_routing(); ?>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Programación', 'convoca-publisher' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="convoca_publisher_enable_scheduler" value="1" <?php checked( get_option( 'convoca_publisher_enable_scheduler', true ) ); ?> />
								<?php echo esc_html__( 'Activar programación de entradas', 'convoca-publisher' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Publica automáticamente las entradas programadas cuando se publican.', 'convoca-publisher' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="cp-notice">
				<p>
					<?php echo esc_html__( 'Los datos y las plantillas de cada red viven en su propia pantalla.', 'convoca-publisher' ); ?>
					<a href="<?php echo esc_url( Vista_Canales::tab_url( 'channels' ) ); ?>"><?php echo esc_html__( 'Ir a Canales', 'convoca-publisher' ); ?></a>
				</p>
			</div>

		<div class="cp-section">
			<h2><?php echo esc_html__( 'Qué hace cada cuenta', 'convoca-publisher' ); ?></h2>
			<input type="hidden" name="<?php echo esc_attr( Policy::POLICY_OPTION ); ?>[*]" value="<?php echo esc_attr( (string) ( Policy::saved()['*'] ?? Policy::AUTO ) ); ?>" />
			<table class="form-table">
				<?php foreach ( convoca_publisher()->get_channels() as $cuenta ) : ?>
					<?php
					$id     = (string) $cuenta->get_id();
					$estado = (string) ( Policy::saved()[ $id ] ?? Policy::saved()['*'] ?? Policy::AUTO );
					?>
					<tr>
						<th scope="row"><label for="convoca_policy_<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $cuenta->get_name() ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( Policy::POLICY_OPTION . '[' . $id . ']' ); ?>" id="convoca_policy_<?php echo esc_attr( $id ); ?>">
								<option value="<?php echo esc_attr( Policy::AUTO ); ?>" <?php selected( $estado, Policy::AUTO ); ?>><?php echo esc_html__( 'Enviar al publicar', 'convoca-publisher' ); ?></option>
								<option value="<?php echo esc_attr( Policy::REVIEW ); ?>" <?php selected( $estado, Policy::REVIEW ); ?>><?php echo esc_html__( 'Dejar propuesta para revisar', 'convoca-publisher' ); ?></option>
								<option value="<?php echo esc_attr( Policy::MANUAL ); ?>" <?php selected( $estado, Policy::MANUAL ); ?>><?php echo esc_html__( 'No enviar: solo a mano', 'convoca-publisher' ); ?></option>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="description"><?php echo esc_html__( 'En modo automático todo envía al publicar salvo lo marcado «solo a mano». «Dejar propuesta» solo funciona con el modo cola y licencia PRO; sin licencia se trata como «solo a mano».', 'convoca-publisher' ); ?></p>
		</div>

		<?php submit_button(); ?>
		</form>
		<?php
	}
	/**
	 * Tabla de enrutado: categoría/etiqueta → cuentas.
	 */
	private static function render_routing(): void {
		?>
						<tr>
						<th scope="row"><?php echo esc_html__( 'Enrutado por categoría o etiqueta', 'convoca-publisher' ); ?></th>
						<td>
							<input type="hidden" name="convoca_publisher_routing[__reset]" value="1" />
							<?php
							$cp_cuentas = convoca_publisher()->get_channels();
							$cp_reglas  = Routing::rules();
							if ( array() === $cp_cuentas ) :
								?>
								<p class="description"><?php echo esc_html__( 'Configura primero una cuenta para poder enrutar.', 'convoca-publisher' ); ?></p>
							<?php else : ?>
								<p class="description"><?php echo esc_html__( 'Sin reglas, todas las cuentas reciben las entradas. Con reglas, solo las cuentas marcadas en las categorías o etiquetas que coinciden; si nada coincide, la entrada no se propone a nadie.', 'convoca-publisher' ); ?></p>
								<table class="widefat striped cp-routing">
									<thead><tr><th><?php echo esc_html__( 'Criterio', 'convoca-publisher' ); ?></th>
									<?php
									foreach ( $cp_cuentas as $cp_cuenta ) :
										?>
										<th><?php echo esc_html( $cp_cuenta->get_name() ); ?></th><?php endforeach; ?>
									</tr></thead>
									<tbody>
									<?php self::render_routing_rows( $cp_cuentas, $cp_reglas ); ?>
									</tbody>
								</table>
							<?php endif; ?>
						</td>
					</tr>
					<?php
	}
	/**
	 * Filas de la tabla de enrutado.
	 *
	 * @param array<string, object> $cp_cuentas Cuentas.
	 * @param array<string, string[]> $cp_reglas Reglas guardadas.
	 */
	private static function render_routing_rows( array $cp_cuentas, array $cp_reglas ): void {
						$cp_grupos = array(
							'cat' => array(
								__( 'Categorías', 'convoca-publisher' ), get_terms(
									array(
									'taxonomy' => 'category', 'hide_empty' => false, 'number' => 100
									)
								)
							),
							'tag' => array(
								__( 'Etiquetas', 'convoca-publisher' ), get_terms(
									array(
									'taxonomy' => 'post_tag', 'hide_empty' => false, 'number' => 100
									)
								)
							),
						);
						foreach ( $cp_grupos as $cp_tipo => $cp_grupo ) :
							$cp_terminos = is_array( $cp_grupo[1] ) ? $cp_grupo[1] : array();
							if ( array() === $cp_terminos ) :
								continue;
							endif;
							?>
										<tr><th colspan="<?php echo esc_attr( (string) ( count( $cp_cuentas ) + 1 ) ); ?>"><strong><?php echo esc_html( $cp_grupo[0] ); ?></strong></th></tr>
										<?php foreach ( $cp_terminos as $cp_termino ) : ?>
											<?php $cp_key = $cp_tipo . '_' . (int) $cp_termino->term_id; ?>
											<tr>
												<td><?php echo esc_html( $cp_termino->name ); ?></td>
												<?php foreach ( $cp_cuentas as $cp_id => $cp_cuenta ) : ?>
													<td>
														<input type="hidden" name="convoca_publisher_routing[<?php echo esc_attr( $cp_key ); ?>][]" value="" />
														<input type="checkbox" name="convoca_publisher_routing[<?php echo esc_attr( $cp_key ); ?>][]" value="<?php echo esc_attr( (string) $cp_id ); ?>" <?php checked( in_array( (string) $cp_id, (array) ( $cp_reglas[ $cp_key ] ?? array() ), true ) ); ?> />
													</td>
												<?php endforeach; ?>
											</tr>
										<?php endforeach; ?>
									<?php endforeach; ?>
					<?php
	}
}
