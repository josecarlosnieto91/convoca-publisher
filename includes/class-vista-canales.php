<?php
/**
 * Las pantallas de canales: la pestaña, la ficha de una cuenta y su estado.
 *
 * Es el grupo más acoplado de `Admin` (577 líneas) y aun así no llama a nada de fuera de él.
 * Los métodos públicos conservan su delegado en `Admin` porque las pruebas los usan.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canales.
 */
final class Vista_Canales {
	public static function render_channels_tab(): void {
		$networks = Plugin::networks();
		$accounts = convoca_publisher()->get_channels();

		$verify_result = get_transient( 'convoca_publisher_verify_result_' . get_current_user_id() );

		if ( false !== $verify_result ) {
			delete_transient( 'convoca_publisher_verify_result_' . get_current_user_id() );
			?>
			<div class="notice <?php echo ! empty( $verify_result['success'] ) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
				<p><?php echo esc_html( ( ! empty( $verify_result['success'] ) ? '✅ ' : '❌ ' ) . ( $verify_result['message'] ?? '' ) ); ?></p>
			</div>
			<?php
		}

		if ( empty( $networks ) ) {
			?>
			<div class="cp-notice cp-notice--warn">
				<p><?php echo esc_html__( 'No se ha cargado ninguna red. Es un fallo del plugin, no de tu configuración: revisa que la carpeta includes/channels esté completa.', 'convoca-publisher' ); ?></p>
			</div>
			<?php

			return;
		}

		if ( empty( $accounts ) ) {
			?>
			<div class="cp-notice">
				<h2><?php echo esc_html__( 'Por dónde empezar', 'convoca-publisher' ); ?></h2>
				<p><?php echo esc_html__( 'Todavía no hay ninguna cuenta configurada: mientras no la haya, el plugin no publica en ninguna red. El orden que menos guerra da:', 'convoca-publisher' ); ?></p>
				<ol class="cp-steps">
					<li>
						<strong><?php echo esc_html__( 'Telegram', 'convoca-publisher' ); ?></strong> —
						<?php echo esc_html__( 'un bot con BotFather y el identificador del chat. Dos minutos y se verifica en un clic.', 'convoca-publisher' ); ?>
					</li>
					<li>
						<strong><?php echo esc_html__( 'Mastodon', 'convoca-publisher' ); ?></strong> —
						<?php echo esc_html__( 'la dirección de tu instancia y un token de acceso. No caduca por sí solo.', 'convoca-publisher' ); ?>
					</li>
					<li>
						<strong><?php echo esc_html__( 'El resto', 'convoca-publisher' ); ?></strong> —
						<?php echo esc_html__( 'Facebook, LinkedIn, Twitter/X, TikTok y Google piden cuenta de desarrollador y tokens que caducan cada cierto tiempo.', 'convoca-publisher' ); ?>
					</li>
				</ol>
				<?php if ( isset( $networks['telegram'] ) ) : ?>
					<p><a class="button button-primary" href="
					<?php
					echo esc_url(
						self::tab_url(
							'channels',
							array(
								'canal' => 'telegram',
								'nueva' => 1,
							)
						)
					);
					?>
																"><?php echo esc_html__( 'Empezar por Telegram', 'convoca-publisher' ); ?></a></p>
				<?php endif; ?>
			</div>
			<?php
		}

		?>
		<p class="description">
			<?php echo esc_html__( 'Una cuenta es una cuenta: puedes tener varias de la misma red (la página y el grupo de Facebook, dos canales de Telegram) cada una con sus credenciales y su plantilla.', 'convoca-publisher' ); ?>
		</p>

		<?php foreach ( $networks as $network_id => $network ) : ?>
			<?php
			$own  = array_values(
				array_filter(
					$accounts,
					static fn( object $account ): bool => $account instanceof Channel_Profile && $account->get_channel_id() === $network_id
				)
			);
			$free = Profile_Store::LIMIT_PER_NETWORK - count( $own );
			?>
			<div class="cp-section">
				<div class="cp-card__head">
					<h2><?php echo Icons::svg( (string) $network_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio. ?><?php echo esc_html( $network->get_name() ); ?></h2>
					<?php if ( empty( $own ) ) : ?>
						<?php
						// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado.
						echo self::status_badge(
							array(
								'key'   => 'missing',
								'class' => 'cp-status--off',
								'icon'  => '❌',
								'label' => __( 'Falta token', 'convoca-publisher' ),
							)
						);
						// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					<?php else : ?>
						<span class="cp-status cp-status--ok">
							<?php
							printf(
								/* translators: %d: número de cuentas de esa red */
								esc_html( _n( '%d cuenta', '%d cuentas', count( $own ), 'convoca-publisher' ) ),
								(int) count( $own )
							);
							?>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( empty( $own ) ) : ?>
					<p class="description"><?php echo esc_html__( 'Esta red no publica todavía: no tiene ninguna cuenta configurada.', 'convoca-publisher' ); ?></p>
					<?php if ( '' !== self::token_help( (string) $network_id ) ) : ?>
						<p class="description"><?php echo self::token_help( (string) $network_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado en token_help(). ?></p>
					<?php endif; ?>
				<?php else : ?>
					<table class="wp-list-table widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html__( 'Cuenta', 'convoca-publisher' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Estado', 'convoca-publisher' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Acciones', 'convoca-publisher' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $own as $account ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $account->get_name() ); ?></strong>
										<div class="description"><?php echo esc_html( $account->get_id() ); ?></div>
									</td>
									<td>
										<?php echo self::status_badge( self::channel_status( $account ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>
									</td>
									<td>
										<a class="button" href="<?php echo esc_url( self::tab_url( 'channels', array( 'canal' => $account->get_id() ) ) ); ?>">
											<?php echo esc_html__( 'Configurar', 'convoca-publisher' ); ?>
										</a>
										<?php echo self::verify_button( $account ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( $free > 0 ) : ?>
					<p>
						<a class="button" href="
						<?php
						echo esc_url(
							self::tab_url(
								'channels',
								array(
									'canal' => $network_id,
									'nueva' => 1,
								)
							)
						);
						?>
												">
							<?php echo esc_html__( 'Añadir cuenta', 'convoca-publisher' ); ?>
						</a>
					</p>
				<?php else : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %d: límite de cuentas por red */
							esc_html__( 'Límite alcanzado: %d cuentas por red. Borra una para añadir otra.', 'convoca-publisher' ),
							(int) Profile_Store::LIMIT_PER_NETWORK
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Pantalla de un canal: estado, campos, verificación, plantilla y su guía, todo
	 * junto. Es el sitio único de un canal: no hay que ir a otra pestaña a buscar nada.
	 */
	public static function render_channel_screen( \ConvocaPublisher\Channels\ChannelInterface $channel ): void {
		$account      = $channel instanceof Channel_Profile ? $channel : null;
		$networks     = Plugin::networks();
		$network_id   = $account ? $account->get_channel_id() : $channel->get_id();
		$network      = $networks[ $network_id ] ?? $channel;
		$fields       = $network->get_settings_fields();
		$template_key = 'convoca_publisher_' . $network_id . '_template';
		$settings     = $account ? $account->get_settings() : array();
		$status       = $account ? self::channel_status( $account ) : array();
		$guardado     = isset( $_GET['guardado'] );
		$new_account  = ! $account;

		$nombre = $account
			? $account->get_name()
			: sprintf(
				/* translators: %s: nombre de la red */
				__( '%s — cuenta nueva', 'convoca-publisher' ),
				$network->get_name()
			);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo Icons::svg( (string) $network_id, 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio. ?><?php echo esc_html( $nombre ); ?></h1>
			<?php if ( $account ) : ?>
				<?php echo self::status_badge( self::channel_status( $account ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( self::tab_url( 'channels' ) ); ?>">&larr; <?php echo esc_html__( 'Todos los canales', 'convoca-publisher' ); ?></a>
			</p>

			<?php if ( $guardado ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Cuenta guardada.', 'convoca-publisher' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $account || ! $account->is_available() ) : ?>
				<div class="cp-notice cp-notice--warn">
					<p><?php echo esc_html__( 'Mientras falten datos, esta cuenta no publicará nada. Rellénalos, guarda y usa «Verificar conexión».', 'convoca-publisher' ); ?></p>
					<?php if ( '' !== self::token_help( (string) $network_id ) ) : ?>
						<p><?php echo self::token_help( (string) $network_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado en token_help(). ?></p>
					<?php endif; ?>
				</div>
			<?php elseif ( ! empty( $status['detail'] ) ) : ?>
				<div class="cp-notice cp-notice--warn">
					<p><?php echo esc_html( $status['detail'] ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="convoca_publisher_save_account" />
				<input type="hidden" name="red" value="<?php echo esc_attr( $network_id ); ?>" />
				<input type="hidden" name="cuenta" value="<?php echo esc_attr( $account ? $account->get_id() : '' ); ?>" />
				<?php wp_nonce_field( 'convoca_publisher_save_account' ); ?>

				<div class="cp-section">
					<h2><?php echo esc_html__( 'Esta cuenta', 'convoca-publisher' ); ?></h2>
					<div class="cp-field">
						<label for="cuenta_nombre"><?php echo esc_html__( 'Nombre', 'convoca-publisher' ); ?></label>
						<input type="text" id="cuenta_nombre" name="cuenta_nombre" value="<?php echo esc_attr( $nombre ); ?>" class="cp-input regular-text" />
						<p class="description"><?php echo esc_html__( 'Con qué nombre la reconoces. Por ejemplo: «Telegram — Cuenta principal», «Facebook — Grupo».', 'convoca-publisher' ); ?></p>
					</div>

					<?php if ( $account ) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: identificador de la cuenta */
								esc_html__( 'Identificador: %s (es el que usan la cola y el historial).', 'convoca-publisher' ),
								esc_html( $account->get_id() )
							);
							?>
						</p>
					<?php endif; ?>
				</div>

				<div class="cp-section">
					<h2><?php echo esc_html__( 'Credenciales y datos', 'convoca-publisher' ); ?></h2>
					<?php if ( empty( $fields ) ) : ?>
						<p><?php echo esc_html__( 'Esta red no necesita configuración.', 'convoca-publisher' ); ?></p>
					<?php endif; ?>

					<?php foreach ( $fields as $key => $field ) : ?>
						<?php if ( $key === $template_key ) : ?>
							<?php continue; ?>
						<?php endif; ?>

						<?php
						$type  = 'password' === $field['type'] ? 'password' : 'text';
						$value = (string) ( $settings[ $key ] ?? '' );
						?>
						<div class="cp-field">
							<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['title'] ); ?></label>

							<input
								type="<?php echo esc_attr( $type ); ?>"
								id="<?php echo esc_attr( $key ); ?>"
								name="<?php echo esc_attr( $key ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
								class="cp-input regular-text"
							/>

							<?php if ( 'password' === $type ) : ?>
								<button
									type="button"
									class="button-link"
									data-cp-toggle="#<?php echo esc_attr( $key ); ?>"
									data-cp-show="<?php echo esc_attr__( 'Mostrar', 'convoca-publisher' ); ?>"
									data-cp-hide="<?php echo esc_attr__( 'Ocultar', 'convoca-publisher' ); ?>"
									aria-pressed="false"
								><?php echo esc_html__( 'Mostrar', 'convoca-publisher' ); ?></button>
							<?php endif; ?>

							<?php if ( ! empty( $field['description'] ) ) : ?>
								<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php /* Solo en la pantalla de una cuenta: en la del canal no hay plantilla propia que enseñar (la de la red se edita en la pestaña Plantillas) y salía un campo vacío que parecía no hacer nada. */ ?>
				<?php if ( isset( $fields[ $template_key ] ) && $account ) : ?>
					<div class="cp-section">
						<h2><?php echo esc_html__( 'Plantilla de esta cuenta', 'convoca-publisher' ); ?></h2>
						<div class="cp-field">
							<label for="cuenta_plantilla"><?php echo esc_html__( 'Mensaje', 'convoca-publisher' ); ?></label>
							<textarea
								id="cuenta_plantilla"
								name="cuenta_plantilla"
								rows="3"
								class="cp-input cp-input--wide"
								placeholder="<?php echo esc_attr__( 'Usar la plantilla de la red', 'convoca-publisher' ); ?>"
							><?php echo esc_textarea( $account->get_template() ); ?></textarea>
							<p class="description">
								<?php echo esc_html__( 'Déjalo vacío para usar la plantilla de la red (pestaña Plantillas). Variables: {title}, {excerpt}, {url}, {hashtags}, {date}, {author}.', 'convoca-publisher' ); ?>
							</p>
						</div>
					</div>
				<?php endif; ?>

				<?php submit_button( $new_account ? __( 'Crear cuenta', 'convoca-publisher' ) : __( 'Guardar cambios', 'convoca-publisher' ) ); ?>
			</form>

			<?php if ( $account ) : ?>
				<div class="cp-section">
					<h2><?php echo esc_html__( 'Verificar la conexión', 'convoca-publisher' ); ?></h2>
					<p><?php echo esc_html__( 'Pregunta a la red si la credencial de esta cuenta sigue sirviendo. No publica nada ni gasta cuota de envío.', 'convoca-publisher' ); ?></p>
					<p><?php echo self::verify_button( $account ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado. ?></p>
				</div>
			<?php endif; ?>

			<div class="cp-section">
				<h2><?php echo esc_html__( 'Cómo conseguir estas credenciales', 'convoca-publisher' ); ?></h2>
				<p>
					<a href="<?php echo esc_url( self::tab_url( 'guide' ) . '#canal-' . $network_id ); ?>">
						<?php
						printf(
							/* translators: %s: nombre de la red social */
							esc_html__( 'Guía paso a paso de %s', 'convoca-publisher' ),
							esc_html( $network->get_name() )
						);
						?>
					</a>
				</p>
			</div>

			<?php if ( $account ) : ?>
				<div class="cp-section">
					<h2><?php echo esc_html__( 'Borrar esta cuenta', 'convoca-publisher' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Se borra su configuración. Las entradas ya publicadas y el historial se quedan como están.', 'convoca-publisher' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="convoca_publisher_delete_account" />
						<input type="hidden" name="cuenta" value="<?php echo esc_attr( $account->get_id() ); ?>" />
						<?php wp_nonce_field( 'convoca_publisher_delete_account' ); ?>
						<button
							type="submit"
							class="button button-link-delete"
							data-cp-confirm="<?php echo esc_attr__( '¿Borrar esta cuenta?', 'convoca-publisher' ); ?>"
						><?php echo esc_html__( 'Borrar cuenta', 'convoca-publisher' ); ?></button>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Estado de un canal, tal y como se ve en su tarjeta y en su pantalla.
	 *
	 * ✅ Configurado · ❌ Falta token · ⚠️ Error al verificar · 🔑 Necesita reconexión.
	 * El resultado de la última verificación solo cuenta si la configuración no ha
	 * cambiado desde entonces: si el token es otro, el estado viejo no vale.
	 *
	 * @return array{key: string, class: string, icon: string, label: string, detail?: string}
	 */
	public static function channel_status( \ConvocaPublisher\Channels\ChannelInterface $channel ): array {
		$channel_id = $channel->get_id();

		if ( ! $channel->is_available() ) {
			return array(
				'key'   => 'missing',
				'class' => 'cp-status--off',
				'icon'  => '❌',
				'label' => __( 'Falta token', 'convoca-publisher' ),
			);
		}

		$estados = (array) get_option( 'convoca_publisher_verify_status', array() );
		$estado  = isset( $estados[ $channel_id ] ) ? (array) $estados[ $channel_id ] : array();

		if ( $estado && ( $estado['fingerprint'] ?? '' ) !== self::channel_fingerprint( $channel ) ) {
			$estado = array(); // La configuración cambió: el resultado anterior ya no dice nada.
		}

		if ( $estado && empty( $estado['success'] ) ) {
			$detalle    = (string) ( $estado['message'] ?? '' );
			$reconectar = self::looks_like_auth_error( $detalle );

			return array(
				'key'    => $reconectar ? 'reconnect' : 'error',
				'class'  => $reconectar ? 'cp-status--warn' : 'cp-status--fail',
				'icon'   => $reconectar ? '🔑' : '⚠️',
				'label'  => $reconectar
					? __( 'Necesita reconexión', 'convoca-publisher' )
					: __( 'Error al verificar', 'convoca-publisher' ),
				'detail' => $detalle,
			);
		}

		// Verificada, sí, pero ¿cuándo? Las redes con token que caduca solo (Facebook y
		// LinkedIn, 60 días) fallan un día sin que nadie haya tocado nada: con la fecha de la
		// última comprobación se avisa antes, en vez de enterarse por un envío perdido.
		$verificado_en = 0;

		if ( isset( $estado['time'] ) && is_string( $estado['time'] ) && '' !== $estado['time'] ) {
			$fecha         = new \DateTimeImmutable( $estado['time'], wp_timezone() );
			$verificado_en = $fecha->getTimestamp();
		}

		$red = $channel instanceof Channel_Profile ? $channel->get_channel_id() : $channel->get_id();

		if ( Credential_Health::needs_attention( $red, $verificado_en ) ) {
			$caducada = 'caducada' === Credential_Health::state( $red, $verificado_en );

			return array(
				'key'    => $caducada ? 'reconnect' : 'expiring',
				'class'  => 'cp-status--warn',
				'icon'   => $caducada ? '🔑' : '⏳',
				'label'  => $caducada
					? __( 'Puede haber caducado', 'convoca-publisher' )
					: __( 'Caduca pronto', 'convoca-publisher' ),
				'detail' => Credential_Health::message( $red, $verificado_en ),
			);
		}

		// Nunca comprobada (publisher#10): tener el token guardado no es que funcione. Antes caía en el
		// ✅ «Configurado» sin haber hecho ni una llamada, que es justo lo que no puede decir la pantalla.
		if ( 0 === $verificado_en ) {
			return array(
				'key'    => 'unverified',
				'class'  => 'cp-status--warn',
				'icon'   => '⚠️',
				'label'  => __( 'Sin comprobar', 'convoca-publisher' ),
				'detail' => Credential_Health::message( $red, 0 ),
			);
		}

		return array(
			'key'   => 'ok',
			'class' => 'cp-status--ok',
			'icon'  => '✅',
			'label' => __( 'Configurado', 'convoca-publisher' ),
		);
	}

	/**
	 * URL de una pestaña del panel (una sola forma de construirla en todo el plugin).
	 *
	 * @param array<string, mixed> $extra
	 */
	public static function tab_url( string $tab, array $extra = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'convoca-publisher',
					'tab'  => $tab,
				),
				$extra
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Dónde se consigue el token de una red.
	 *
	 * El aviso «Falta token» era un callejón sin salida: decía qué faltaba y no de dónde sacarlo (issue #8).
	 * Aquí va el sitio concreto donde se genera, sin describir interfaces que cambian: el nombre del panel y
	 * su dirección. Lo que se pega en el campo es lo que dé esa herramienta.
	 *
	 * @param string $network_id Identificador de la red (`facebook`, `telegram`, …).
	 * @return string Marcado **ya escapado** (se imprime sin volver a escaparlo), o cadena vacía.
	 */
	public static function token_help( string $network_id ): string {
		$paneles = array(
			'facebook'         => array( 'Meta for Developers', 'https://developers.facebook.com/apps/' ),
			'googlemybusiness' => array( 'Google Cloud Console', 'https://console.cloud.google.com/apis/credentials' ),
			'linkedin'         => array( 'LinkedIn Developer Portal', 'https://www.linkedin.com/developers/apps' ),
			'mastodon'         => array( 'los ajustes de tu propia instancia (Preferencias → Desarrollo)', '' ),
			'telegram'         => array( '@BotFather, en Telegram', 'https://t.me/BotFather' ),
			'tiktok'           => array( 'TikTok for Developers', 'https://developers.tiktok.com/' ),
			'twitter'          => array( 'X Developer Portal', 'https://developer.x.com/' ),
		);

		if ( ! isset( $paneles[ $network_id ] ) ) {
			return '';
		}

		list( $panel, $url ) = $paneles[ $network_id ];

		if ( '' === $url ) {
			return sprintf(
				/* translators: %s: nombre del sitio donde se genera el token. */
				esc_html__( 'El token se genera en %s.', 'convoca-publisher' ),
				esc_html( $panel )
			);
		}

		return sprintf(
			/* translators: 1: nombre del panel de desarrollador, 2: enlace a ese panel. */
			esc_html__( 'El token se genera en %1$s: %2$s', 'convoca-publisher' ),
			esc_html( $panel ),
			'<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>'
		);
	}

	/**
	 * Botón de «Verificar conexión» de un canal (llama a su API y guarda el resultado).
	 */
	public static function verify_button( \ConvocaPublisher\Channels\ChannelInterface $channel ): string {
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=convoca_publisher_verify_channel&channel=' . $channel->get_id() ),
			'convoca_publisher_verify_channel'
		);

		return sprintf(
			'<a href="%1$s" class="button">%2$s %3$s</a>',
			esc_url( $url ),
			esc_html( '🔍' ),
			esc_html__( 'Verificar conexión', 'convoca-publisher' )
		);
	}

	/**
	 * Etiqueta de estado (marcado ya escapado: no volver a escaparla).
	 *
	 * @param array<string, mixed> $status
	 */
	public static function status_badge( array $status ): string {
		return sprintf(
			'<span class="cp-status %1$s">%2$s %3$s</span>',
			esc_attr( $status['class'] ),
			esc_html( $status['icon'] ),
			esc_html( $status['label'] )
		);
	}

	/**
	 * Huella de la configuración de un canal: sirve para saber si el resultado de una
	 * verificación sigue siendo válido.
	 */
	public static function channel_fingerprint( \ConvocaPublisher\Channels\ChannelInterface $channel ): string {
		if ( $channel instanceof Channel_Profile ) {
			return md5( (string) wp_json_encode( $channel->get_settings() ) );
		}

		$valores = array();

		foreach ( array_keys( $channel->get_settings_fields() ) as $key ) {
			$valores[ $key ] = (string) get_option( (string) $key, '' );
		}

		return md5( (string) wp_json_encode( $valores ) );
	}

	/**
	 * ¿El error suena a credencial caducada o sin permiso?
	 */
	public static function looks_like_auth_error( string $message ): bool {
		$agujas = array( 'token', '401', '403', 'expired', 'caduc', 'invalid', 'unauthorized', 'permission', 'forbidden', 'oauth' );

		foreach ( $agujas as $aguja ) {
			if ( false !== stripos( $message, $aguja ) ) {
				return true;
			}
		}

		return false;
	}
}
