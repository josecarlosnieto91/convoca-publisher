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
						<th scope="row"><?php echo esc_html__( 'Publicación automática', 'convoca-publisher' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="convoca_publisher_auto_publish" value="1" <?php checked( get_option( 'convoca_publisher_auto_publish', true ) ); ?> />
								<?php echo esc_html__( 'Publicar automáticamente al publicar una entrada', 'convoca-publisher' ); ?>
							</label>
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
			<h2><?php echo esc_html__( 'Moderación previa', 'convoca-publisher' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="convoca_publisher_moderation"><?php echo esc_html__( 'Modo de moderación', 'convoca-publisher' ); ?></label></th>
					<td>
						<select name="convoca_publisher_moderation" id="convoca_publisher_moderation">
							<option value="off" <?php selected( get_option( 'convoca_publisher_moderation', 'off' ), 'off' ); ?>><?php echo esc_html__( 'Desactivada (publicación automática)', 'convoca-publisher' ); ?></option>
							<option value="all" <?php selected( get_option( 'convoca_publisher_moderation', 'off' ), 'all' ); ?>><?php echo esc_html__( 'Todas las publicaciones requieren revisión', 'convoca-publisher' ); ?></option>
							<option value="canal" <?php selected( get_option( 'convoca_publisher_moderation', 'off' ), 'canal' ); ?>><?php echo esc_html__( 'Solo canales seleccionados', 'convoca-publisher' ); ?></option>
						</select>
						<p class="description"><?php echo esc_html__( 'Cuando está activa, las publicaciones quedan pendientes de revisión en la pestaña Moderación antes de enviarse a las redes.', 'convoca-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Canales con moderación', 'convoca-publisher' ); ?></th>
					<td>
						<input type="hidden" name="convoca_publisher_moderation_channels[]" value="" />
						<?php foreach ( convoca_publisher()->get_channels() as $channel ) : ?>
							<?php $checked = in_array( $channel->get_id(), (array) get_option( 'convoca_publisher_moderation_channels', array() ), true ) ? 'checked' : ''; ?>
							<label class="cp-check" data-cp-moderation-group>
								<input type="checkbox" name="convoca_publisher_moderation_channels[]" value="<?php echo esc_attr( $channel->get_id() ); ?>" data-cp-moderation-channel <?php echo esc_attr( $checked ); ?>> <?php echo esc_html( $channel->get_name() ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php echo esc_html__( 'Se aplican cuando el modo es "Solo canales seleccionados".', 'convoca-publisher' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button(); ?>
		</form>
		<?php
	}
}
