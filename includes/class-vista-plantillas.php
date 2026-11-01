<?php
/**
 * La pestaña de plantillas, con sus herramientas y su lista de candidatas.
 *
 * `test_candidates()` sigue siendo pública en `Admin` como delegada: la usan las pruebas.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plantillas de mensaje.
 */
final class Vista_Plantillas {

	public static function render(): void {
		// Plantillas por RED (es lo que lee el publicador cuando la cuenta no tiene la
		// suya). Las de cuenta se editan en la pantalla de cada cuenta: aquí no pintan.
		$networks = Plugin::networks();
		?>
		<div class="cp-section">
			<h2><?php echo esc_html__( 'Plantillas de mensaje', 'convoca-publisher' ); ?></h2>

			<div class="cp-help">
				<p><strong><?php echo esc_html__( 'Variables disponibles:', 'convoca-publisher' ); ?></strong></p>
				<p>
					<?php foreach ( Publisher::variables() as $variable => $para_que ) : ?>
						<code><?php echo esc_html( $variable ); ?></code> — <?php echo esc_html( $para_que ); ?><br>
					<?php endforeach; ?>
				</p>

				<?php $candidatas = self::candidatas(); ?>
				<?php if ( $candidatas ) : ?>
					<p class="cp-field">
						<label for="cp-plantilla-entrada"><?php echo esc_html__( 'Ver con la entrada', 'convoca-publisher' ); ?></label>
						<select id="cp-plantilla-entrada">
							<?php foreach ( $candidatas as $candidata ) : ?>
								<option value="<?php echo esc_attr( (string) $candidata['id'] ); ?>"><?php echo esc_html( $candidata['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="description"><?php echo esc_html__( 'La vista previa y el contador de cada plantilla usan esta entrada.', 'convoca-publisher' ); ?></span>
					</p>
				<?php endif; ?>
				<p><?php echo esc_html__( 'Pulsa una variable para insertarla donde tengas el cursor. La vista previa se actualiza sola y te dice si el mensaje cabe en esa red o si se recortará.', 'convoca-publisher' ); ?></p>
				<p><?php echo esc_html__( 'Se usa la primera que esté puesta, de lo más concreto a lo más general: lo que se escribe para un envío concreto, lo de esa entrada, la plantilla de la cuenta (en su pantalla), la de su red y la global. Si no hay ninguna, la de fábrica de cada red, que se ve bajo cada campo.', 'convoca-publisher' ); ?></p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'convoca_publisher_settings' ); ?>

				<h3><?php echo esc_html__( 'Plantilla global', 'convoca-publisher' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Mensaje por defecto', 'convoca-publisher' ); ?></th>
						<td>
							<textarea id="cp-plantilla-global" name="convoca_publisher_message_template" rows="3" class="cp-input cp-input--wide"><?php echo esc_textarea( (string) get_option( 'convoca_publisher_message_template', '{title} — {url} {hashtags}' ) ); ?></textarea>
							<?php self::herramientas( '#cp-plantilla-global', '' ); ?>
							<p class="description"><?php echo esc_html__( 'Se usa cuando un canal no tiene su propia plantilla.', 'convoca-publisher' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php echo esc_html__( 'Plantillas por red', 'convoca-publisher' ); ?></h3>
				<p><?php echo esc_html__( 'Déjalo vacío para usar la plantilla global. Cada cuenta puede tener la suya propia en su pantalla.', 'convoca-publisher' ); ?></p>

				<?php
				foreach ( $networks as $channel ) :
					$tkey = 'convoca_publisher_' . $channel->get_id() . '_template';
					$tval = get_option( $tkey, '' );
					?>
				<div class="cp-channel-template">
					<h4><?php echo esc_html( $channel->get_name() ); ?></h4>
					<textarea id="cp-plantilla-<?php echo esc_attr( $channel->get_id() ); ?>" name="<?php echo esc_attr( $tkey ); ?>" rows="3" class="cp-input cp-input--wide" placeholder="<?php echo esc_attr__( 'Usar plantilla global', 'convoca-publisher' ); ?>"><?php echo esc_textarea( (string) $tval ); ?></textarea>
					<?php
					self::herramientas(
						'#cp-plantilla-' . $channel->get_id(),
						(string) $channel->get_id(),
						Publisher::factory_templates()[ $channel->get_id() ] ?? '{title} — {url}'
					);
					?>
					<p class="description">
						<?php if ( '' === trim( (string) $tval ) ) : ?>
							<?php echo esc_html__( 'Ahora mismo se usa la de fábrica para esta red:', 'convoca-publisher' ); ?>
						<?php else : ?>
							<?php echo esc_html__( 'Se usa esta en vez de la global. La de fábrica para esta red es:', 'convoca-publisher' ); ?>
						<?php endif; ?>
						<code><?php echo esc_html( Publisher::factory_templates()[ $channel->get_id() ] ?? '{title} — {url}' ); ?></code>
					</p>
				</div>
				<?php endforeach; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
	/**
	 * Botones de variables, vuelta a la de fábrica y panel de vista previa de un campo.
	 *
	 * @param string $campo   Selector del área de texto a la que afectan.
	 * @param string $red     Red de la que se comprueban los límites ('' = sin contador).
	 * @param string $fabrica Plantilla de fábrica a la que vuelve el botón.
	 */
	private static function herramientas( string $campo, string $red, string $fabrica = '' ): void {
		?>
		<p class="cp-plantilla-botones">
			<?php foreach ( Publisher::variables() as $variable => $para_que ) : ?>
				<button
					type="button"
					class="button button-small"
					data-cp-insert="<?php echo esc_attr( $variable ); ?>"
					data-cp-into="<?php echo esc_attr( $campo ); ?>"
					title="<?php echo esc_attr( $para_que ); ?>"
				><?php echo esc_html( $variable ); ?></button>
			<?php endforeach; ?>
			<?php if ( '' !== $fabrica ) : ?>
				<button
					type="button"
					class="button button-small cp-plantilla-fabrica"
					data-cp-reset="<?php echo esc_attr( $campo ); ?>"
					data-cp-factory="<?php echo esc_attr( $fabrica ); ?>"
				><?php echo esc_html__( 'Volver a la de fábrica', 'convoca-publisher' ); ?></button>
			<?php endif; ?>
		</p>
		<?php if ( '' !== $red ) : ?>
			<div class="cp-preview" data-cp-preview data-cp-network="<?php echo esc_attr( $red ); ?>" data-cp-into="<?php echo esc_attr( $campo ); ?>"></div>
		<?php endif; ?>
		<?php
	}
	/**
	 * Entradas candidatas para probar un envío.
	 *
	 * @param int $limit Cuántas entradas traer.
	 * @return array<int, array{id: int, label: string}>
	 */
	public static function candidatas( int $limit = 30 ): array {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$candidatas = array();

		foreach ( $ids as $id ) {
			$id = (int) $id;

			$candidatas[] = array(
				'id'    => $id,
				'label' => sprintf( '%s — %s', get_the_title( $id ), get_the_date( 'd/m/Y', $id ) ),
			);
		}

		return $candidatas;
	}
}
