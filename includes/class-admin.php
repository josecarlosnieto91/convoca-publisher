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

namespace ConvocaPublisher;

defined( 'ABSPATH' ) || exit;

class Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( self::class, 'aviso_redes_pro' ) );
		// Los enlaces de la página de logs apuntan a admin.php?action=convoca_publisher_*,
		// pero estos handlers se quedaron con el prefijo cp_ en el refactor de prefijos: el
		// navegador pedía una acción que nadie atendía y los dos botones (Limpiar historial y
		// Reintentar) no hacían nada. Ver docs/deuda-tecnica.md.
		add_action( 'admin_action_convoca_publisher_delete_log', array( self::class, 'handle_delete_log' ) );
		add_action( 'admin_action_convoca_publisher_retry_log', array( self::class, 'handle_retry_log' ) );
		add_action( 'admin_post_convoca_publisher_verify_channel', array( self::class, 'handle_verify_channel' ) );
		add_action( 'admin_post_convoca_publisher_save_account', array( self::class, 'handle_save_account' ) );
		add_action( 'admin_post_convoca_publisher_delete_account', array( self::class, 'handle_delete_account' ) );
		add_action( 'admin_post_convoca_publisher_queue_reschedule', array( self::class, 'handle_queue_reschedule' ) );
		add_action( 'admin_post_convoca_publisher_queue_cancel', array( self::class, 'handle_queue_cancel' ) );
		add_action( 'admin_post_convoca_publisher_queue_spacing', array( self::class, 'handle_queue_spacing' ) );
		add_action( 'admin_post_convoca_publisher_queue_send_stuck', array( self::class, 'handle_queue_send_stuck' ) );
		add_action( 'admin_post_convoca_publisher_share_now', array( self::class, 'handle_share_now' ) );
		add_filter( 'post_row_actions', array( self::class, 'row_action' ), 10, 2 );
		add_action( 'admin_notices', array( self::class, 'shared_notice' ) );
		add_action( 'admin_post_convoca_publisher_approve_review', array( self::class, 'handle_approve_review' ) );
		add_action( 'admin_post_convoca_publisher_reject_review', array( self::class, 'handle_reject_review' ) );
	}

	public static function add_menu_page(): void {
		add_menu_page(
			esc_html__( 'Convoca Publisher', 'convoca-publisher' ),
			esc_html__( 'Convoca Publisher', 'convoca-publisher' ),
			'manage_options',
			'convoca-publisher',
			array( self::class, 'render_page' ),
			'dashicons-share',
			80
		);

		add_submenu_page(
			'convoca-publisher',
			esc_html__( 'Historial', 'convoca-publisher' ),
			esc_html__( 'Historial', 'convoca-publisher' ),
			'manage_options',
			'convoca-publisher-log',
			array( self::class, 'render_log_page' )
		);

		add_submenu_page(
			'convoca-publisher',
			esc_html__( 'Acerca de', 'convoca-publisher' ),
			esc_html__( 'Acerca de', 'convoca-publisher' ),
			'manage_options',
			'convoca-publisher-about',
			array( self::class, 'render_about_page' )
		);
	}

	public static function register_settings(): void {
		$channels = convoca_publisher()->get_channels();
		foreach ( $channels as $channel ) {
			foreach ( $channel->get_settings_fields() as $key => $field ) {
				if ( str_ends_with( $key, '_template' ) ) {
					// `wp_kses_post` y no `sanitize_text_field`: la segunda borra los saltos
					// de línea, así que una plantilla de varias líneas volvía hecha una sola.
					register_setting(
						'convoca_publisher_settings',
						$key,
						array(
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
							'show_in_rest'      => false,
							'default'           => '',
						)
					);
				} else {
					register_setting(
						'convoca_publisher_settings',
						$key,
						array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'show_in_rest'      => false,
							'default'           => '',
						)
					);
				}
			}
		}

		register_setting(
			'convoca_publisher_settings',
			'convoca_publisher_email_alerts',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
				'show_in_rest'      => false,
				'default'           => '1',
			)
		);
		register_setting(
			'convoca_publisher_settings',
			'convoca_publisher_queue_interval',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( self::class, 'sanitize_queue_interval' ),
				'show_in_rest'      => false,
				'default'           => Queue::DEFAULT_INTERVAL,
			)
		);
		register_setting(
			'convoca_publisher_settings',
			'convoca_publisher_message_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => '{title} — {url} {hashtags}',
			)
		);
		register_setting(
			'convoca_publisher_settings',
			'convoca_publisher_test_channel',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'show_in_rest'      => false,
				'default'           => '',
			)
		);
		register_setting(
			'convoca_publisher_settings',
			'convoca_publisher_auto_publish',
			array(
				'type'    => 'boolean',
				'default' => true,
			)
		);
		register_setting(
			'convoca_publisher_settings',
			'convoca_publisher_enable_scheduler',
			array(
				'type'    => 'boolean',
				'default' => true,
			)
		);
		register_setting(
			'convoca_publisher_settings',
			'convoca_publisher_privacy_acknowledged',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( self::class, 'sanitize_privacy_ack' ),
			)
		);
		register_setting(
			'convoca_publisher_settings',
			'convoca_publisher_moderation',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_moderation_mode' ),
				'show_in_rest'      => false,
				'default'           => 'off',
			)
		);
		register_setting(
			'convoca_publisher_settings',
			'convoca_publisher_moderation_channels',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_moderation_channels' ),
				'show_in_rest'      => false,
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitizar el modo de moderación (off|all|canal).
	 *
	 * @param mixed $value
	 */
	/**
	 * Casilla de ajustes: '1' o '' según venga marcada.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_checkbox( $value ): string {
		return empty( $value ) ? '' : '1';
	}

	/**
	 * Ajuste: cada cuánto como mínimo entre envíos (en segundos; 0 = sin espaciado).
	 *
	 * @param mixed $value
	 * @return int
	 */
	public static function sanitize_queue_interval( $value ): int {
		// OJO: un saneador NO escribe la opción que está saneando.
		//
		// Antes esto llamaba a `Queue::save_interval()`, que guarda con `update_option()`. Al
		// guardar, WordPress pasa otra vez por `sanitize_option` y vuelve a llamar a este
		// método: el saneador se llamaba a sí mismo sin fin hasta reventar la pila, y el
		// guardado terminaba en un 500. Pasaba guardando una plantilla porque el grupo de
		// ajustes se guarda entero desde esa pestaña, aunque el intervalo no se toque.
		return Queue::clamp_interval( (int) $value );
	}

	/**
	 * Sanitiza el modo de moderación.
	 *
	 * @param mixed $value Valor recibido.
	 * @return string
	 */
	public static function sanitize_moderation_mode( $value ): string {
		$value = (string) $value;

		return in_array( $value, array( 'off', 'all', 'canal' ), true ) ? $value : 'off';
	}

	/**
	 * Sanitize the privacy acknowledgement.
	 *
	 * El aviso vive solo en la pestaña Configuración, pero el grupo de opciones se
	 * guarda también desde otras pestañas (Plantillas). Si el campo no viene en
	 * el POST, se conserva el valor guardado en lugar de desmarcarlo.
	 *
	 * @param mixed $value Valor enviado.
	 * @return bool
	 */
	public static function sanitize_privacy_ack( $value ): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Función de saneado del API de ajustes: el nonce de la pantalla lo comprueba options.php antes de llamarla.
		if ( ! isset( $_POST['convoca_publisher_privacy_acknowledged'] ) ) {
			return (bool) get_option( 'convoca_publisher_privacy_acknowledged', false );
		}

		return ! empty( $value );
	}

	/**
	 * Sanitizar la lista de canales con moderación previa.
	 *
	 * @param mixed $value
	 * @return array<int, string>
	 */
	public static function sanitize_moderation_channels( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid = array_keys( convoca_publisher()->get_channels() );
		$clean = array();
		foreach ( $value as $channel ) {
			$channel = sanitize_key( (string) $channel );
			if ( $channel !== '' && in_array( $channel, $valid, true ) ) {
				$clean[] = $channel;
			}
		}

		return $clean;
	}

	public static function enqueue_assets( string $hook ): void {
		$es_pantalla_del_plugin = str_contains( $hook, 'convoca-publisher' );
		$es_editor              = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $es_pantalla_del_plugin && ! $es_editor ) {
			return;
		}

		// Ficheros de verdad, encolados solo donde hacen falta (antes iban en un
		// `wp_add_inline_style` colgado de `dashicons`: dependía de que WordPress
		// encolara ese fichero y el estilo no se podía cachear).
		wp_enqueue_style(
			'convoca-publisher-admin',
			CONVOCA_PUBLISHER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CONVOCA_PUBLISHER_VERSION
		);

		if ( ! $es_pantalla_del_plugin ) {
			return;
		}

		wp_enqueue_script(
			'convoca-publisher-admin',
			CONVOCA_PUBLISHER_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			CONVOCA_PUBLISHER_VERSION,
			true
		);

		// El JS necesita saber a dónde llamar para la vista previa. Los manejadores AJAX
		// existían pero nunca se usaron desde la pantalla (el botón usaba un POST normal).
		wp_localize_script(
			'convoca-publisher-admin',
			'convocaPublisher',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'convoca_publisher_preview' ),
				'i18n'    => array(
					/* translators: %s: número de caracteres que quedan. */
					'quedan' => __( 'Quedan %s caracteres', 'convoca-publisher' ),
					/* translators: %s: número de caracteres de más. */
					'pasado' => __( 'Te pasas por %s caracteres: se recortará antes de enviar. Esto es lo que se mandaría:', 'convoca-publisher' ),
					'error'  => __( 'No se pudo generar la vista previa.', 'convoca-publisher' ),
				),
			)
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$accounts   = convoca_publisher()->get_channels();
		$networks   = Plugin::networks();
		$channel_id = isset( $_GET['canal'] ) ? sanitize_key( wp_unslash( (string) $_GET['canal'] ) ) : '';

		// La pantalla de una cuenta manda sobre las pestañas. Si el id es de una red que
		// todavía no tiene cuenta, es el alta de una cuenta nueva de esa red.
		if ( '' !== $channel_id ) {
			if ( isset( $accounts[ $channel_id ] ) ) {
				self::render_channel_screen( $accounts[ $channel_id ] );

				return;
			}

			if ( isset( $networks[ $channel_id ] ) ) {
				self::render_channel_screen( $networks[ $channel_id ] );

				return;
			}
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'channels';

		$tabs = array(
			'channels'   => __( 'Canales', 'convoca-publisher' ),
			'queue'      => __( 'Cola', 'convoca-publisher' ),
			'settings'   => __( 'Configuración', 'convoca-publisher' ),
			'templates'  => __( 'Plantillas', 'convoca-publisher' ),
			'test'       => __( 'Probar', 'convoca-publisher' ),
			'moderation' => __( 'Moderación', 'convoca-publisher' ),
			'guide'      => __( 'Guía', 'convoca-publisher' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Convoca Publisher', 'convoca-publisher' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( self::tab_url( $slug ) ); ?>" class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $active_tab ) {
				case 'queue':
					self::render_queue_tab();
					break;
				case 'settings':
					self::render_settings_tab();
					break;
				case 'templates':
					self::render_templates_tab();
					break;
				case 'test':
					self::render_test_tab();
					break;
				case 'moderation':
					self::render_moderation_tab();
					break;
				case 'guide':
					self::render_guide_tab();
					break;
				case 'channels':
				default:
					self::render_channels_tab();
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * URL de una pestaña del panel (una sola forma de construirla en todo el plugin).
	 *
	 * @param array<string, mixed> $extra
	 */
	public static function tab_url( string $tab, array $extra = array() ): string {
		return Vista_Canales::tab_url( $tab, $extra );
	}

	/**
	 * Pantalla de un canal: estado, campos, verificación, plantilla y su guía, todo
	 * junto. Es el sitio único de un canal: no hay que ir a otra pestaña a buscar nada.
	 */
	private static function render_channel_screen( \ConvocaPublisher\Channels\ChannelInterface $channel ): void {
		Vista_Canales::render_channel_screen( $channel );
	}

	private static function render_settings_tab(): void {
		Vista_Ajustes::render();
	}

	/**
	 * La pestaña de plantillas delega en su vista.
	 */
	private static function render_templates_tab(): void {
		Vista_Plantillas::render();
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
		return Vista_Canales::token_help( $network_id );
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
		return Vista_Canales::channel_status( $channel );
	}

	/**
	 * Huella de la configuración de un canal: sirve para saber si el resultado de una
	 * verificación sigue siendo válido.
	 */
	public static function channel_fingerprint( \ConvocaPublisher\Channels\ChannelInterface $channel ): string {
		return Vista_Canales::channel_fingerprint( $channel );
	}

	/**
	 * ¿El error suena a credencial caducada o sin permiso?
	 */
	public static function looks_like_auth_error( string $message ): bool {
		return Vista_Canales::looks_like_auth_error( $message );
	}

	/**
	 * Etiqueta de estado (marcado ya escapado: no volver a escaparla).
	 *
	 * @param array<string, mixed> $status
	 */
	public static function status_badge( array $status ): string {
		return Vista_Canales::status_badge( $status );
	}

	/**
	 * Botón de «Verificar conexión» de un canal (llama a su API y guarda el resultado).
	 */
	public static function verify_button( \ConvocaPublisher\Channels\ChannelInterface $channel ): string {
		return Vista_Canales::verify_button( $channel );
	}

	private static function render_channels_tab(): void {
		Vista_Canales::render_channels_tab();
	}

	/**
	 * Entradas recientes que se pueden usar en la prueba de publicación.
	 *
	 * Devuelve el id y la etiqueta ya formateada (título y fecha) para que la pantalla no
	 * consulte nada y esta regla se pueda probar aislada.
	 *
	 * Ojo: aquí NO vale `wp_dropdown_pages()`. Esa función trabaja con `get_pages()`, que
	 * solo devuelve tipos jerárquicos: con `post_type => 'post'` devuelve `false` y no pinta
	 * nada, así que el desplegable salía vacío y parecía que el plugin no tenía nada que probar.
	 *
	 * @param int $limit Cuántas entradas traer.
	 * @return array<int, array{id: int, label: string}>
	 */
	public static function test_candidates( int $limit = 30 ): array {
		return Vista_Plantillas::candidatas( $limit );
	}

	private static function render_test_tab(): void {
		$channels = convoca_publisher()->get_channels();
		?>
		<div class="cp-section">
			<h2><?php echo esc_html__( 'Prueba de publicación', 'convoca-publisher' ); ?></h2>
			<p><?php echo esc_html__( 'Selecciona una entrada reciente y haz clic en "Publicar en redes" para probar la integración.', 'convoca-publisher' ); ?></p>

			<?php $canal_de_pruebas = (string) get_option( 'convoca_publisher_test_channel', '' ); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'convoca_publisher_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Canal de pruebas', 'convoca-publisher' ); ?></th>
						<td>
							<select name="convoca_publisher_test_channel">
								<option value=""><?php echo esc_html__( 'Ninguno: prueba en las redes configuradas', 'convoca-publisher' ); ?></option>
								<?php foreach ( Plugin::accounts() as $cuenta_id => $cuenta ) : ?>
									<option value="<?php echo esc_attr( (string) $cuenta_id ); ?>" <?php selected( $canal_de_pruebas, (string) $cuenta_id ); ?>>
										<?php echo esc_html( $cuenta->get_name() . ' — ' . $cuenta->get_channel_id() ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Si eliges uno, el botón de abajo publica SOLO ahí: es la forma de probar sin que la entrada salga en las redes de verdad. Elige un canal que no sea público (por ejemplo tu Telegram).', 'convoca-publisher' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Guardar canal de pruebas', 'convoca-publisher' ), 'secondary' ); ?>
			</form>

			<form method="post">
				<?php wp_nonce_field( 'convoca_publisher_test_publish', 'convoca_publisher_test_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Entrada', 'convoca-publisher' ); ?></th>
						<td>
							<?php
							$candidatas = self::test_candidates();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Solo para recordar la entrada elegida en el desplegable de la pestaña Probar.
							$elegida = isset( $_POST['convoca_publisher_test_post_id'] ) ? intval( $_POST['convoca_publisher_test_post_id'] ) : 0;
							?>
							<select name="convoca_publisher_test_post_id" id="convoca_publisher_test_post_id">
								<option value=""><?php echo esc_html__( 'Seleccionar entrada...', 'convoca-publisher' ); ?></option>
								<?php foreach ( $candidatas as $candidata ) : ?>
									<option value="<?php echo esc_attr( (string) $candidata['id'] ); ?>" <?php selected( $elegida, $candidata['id'] ); ?>>
										<?php echo esc_html( $candidata['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( ! $candidatas ) : ?>
								<p class="description"><?php echo esc_html__( 'No hay entradas publicadas que probar todavía: publica una y vuelve a esta pestaña.', 'convoca-publisher' ); ?></p>
							<?php endif; ?>
						</td>
						</td>
					</tr>
				</table>
				<p class="sp-test-publish">
					<button type="submit" name="convoca_publisher_do_test" class="button button-primary">
						<?php
						echo '' !== $canal_de_pruebas
							? esc_html__( '🚀 Publicar solo en el canal de pruebas', 'convoca-publisher' )
							: esc_html__( '🚀 Publicar en redes', 'convoca-publisher' );
						?>
					</button>
					<?php if ( '' === $canal_de_pruebas ) : ?>
						<span class="description"><?php echo esc_html__( 'Sin canal de pruebas, esto publica en las redes configuradas.', 'convoca-publisher' ); ?></span>
					<?php endif; ?>
				</p>
			</form>

			<?php
			if ( isset( $_POST['convoca_publisher_do_test'] ) && check_admin_referer( 'convoca_publisher_test_publish', 'convoca_publisher_test_nonce' ) ) {
				$post_id = isset( $_POST['convoca_publisher_test_post_id'] ) ? intval( $_POST['convoca_publisher_test_post_id'] ) : 0;
				if ( $post_id > 0 ) {
					echo '<h3>' . esc_html__( 'Resultado:', 'convoca-publisher' ) . '</h3>';
					$publisher = Publisher::instance();
					if ( $publisher ) {
						// Con canal de pruebas se manda SOLO ahí: probar no puede publicar en
						// las redes reales sin querer.
						$results = '' !== $canal_de_pruebas
							? array( $canal_de_pruebas => $publisher->publish_test( $post_id, $canal_de_pruebas ) )
							: $publisher->publish_post( $post_id, true );
						foreach ( $results as $channel_id => $result ) {
							$icon = $result['success'] ? '✅' : '❌';
							echo '<p>' . esc_html( $icon ) . ' <strong>' . esc_html( $channel_id ) . '</strong>: ';
							if ( $result['success'] ) {
								echo esc_html__( 'Publicado! ID: ', 'convoca-publisher' ) . esc_html( $result['post_id'] ?? '' );
							} else {
								echo esc_html( $result['error'] ?? __( 'La red no ha explicado el fallo. Reintenta la publicacion; si se repite, revisa las credenciales del canal en Ajustes.', 'convoca-publisher' ) );
							}
							echo '</p>';
						}
					}
				}
			}
			?>
		</div>
		<?php
	}

	/**
	 * Historial de publicaciones: con filtros por red, cuenta y estado, y reintento de lo que falló.
	 */
	public static function render_log_page(): void {
		Vista_Registro::log_page();
	}

	/**
	 * Reintentar un envío que falló, desde el historial.
	 */
	public static function handle_retry_log(): void {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$canal   = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['channel'] ) ) : '';

		if ( $post_id <= 0 || '' === $canal || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}

		check_admin_referer( 'convoca_publisher_retry_log_' . $post_id . '_' . $canal );

		$publicador = Publisher::instance();
		$resultado  = $publicador ? $publicador->publish_to_accounts( $post_id, array( $canal ), true, true ) : array();
		$salio      = ! empty( $resultado[ $canal ]['success'] );

		foreach ( Plugin::accounts() as $id => $cuenta ) {
			if ( (string) $id === $canal ) {
				$nombre = $cuenta->get_name();
				break;
			}
		}

		set_transient(
			'convoca_publisher_queue_notice_' . get_current_user_id(),
			$salio
				/* translators: %s: nombre de la cuenta. */
				? sprintf( __( 'Reenviado a %s.', 'convoca-publisher' ), $nombre ?? $canal )
				/* translators: %s: nombre de la cuenta. */
				: sprintf( __( 'No se pudo reenviar a %s. El motivo está en el historial.', 'convoca-publisher' ), $nombre ?? $canal ),
			30
		);
		wp_safe_redirect( admin_url( 'admin.php?page=convoca-publisher-log' ) );
		exit;
	}

	public static function render_about_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Acerca de Convoca Publisher', 'convoca-publisher' ); ?></h1>
			<div class="cp-section">
				<h2><?php echo esc_html__( 'Convoca Publisher', 'convoca-publisher' ); ?> v<?php echo esc_html( CONVOCA_PUBLISHER_VERSION ); ?></h2>
				<p><?php echo esc_html__( 'Plugin de publicación automática en redes sociales para WordPress.', 'convoca-publisher' ); ?></p>
				<p><?php echo esc_html__( 'Parte del ecosistema Convoca.', 'convoca-publisher' ); ?></p>
				<ul>
					<li>📘 <a href="https://github.com/josecarlosnieto91/convoca-publisher" target="_blank">GitHub</a></li>
					<li>🐛 <a href="https://github.com/josecarlosnieto91/convoca-publisher/issues" target="_blank">Reportar un problema</a></li>
				</ul>
				<h3><?php echo esc_html__( 'Canales disponibles', 'convoca-publisher' ); ?></h3>
				<ul>
					<li>✅ Facebook / Instagram</li>
					<li>✅ LinkedIn</li>
					<li>✅ Twitter / X</li>
					<li>✅ TikTok</li>
					<li>✅ Google My Business</li>
					<li>✅ Telegram</li>
					<li>✅ Mastodon</li>
				</ul>
			</div>
		</div>
		<?php
	}

	public static function handle_delete_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}
		check_admin_referer( 'convoca_publisher_delete_log' );
		delete_option( 'convoca_publisher_publish_log' );
		wp_safe_redirect( admin_url( 'admin.php?page=convoca-publisher-log' ) );
		exit;
	}

	/**
	 * Handle the verify channel admin-post action.
	 */
	public static function handle_verify_channel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}
		check_admin_referer( 'convoca_publisher_verify_channel' );
		$channel_id = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
		$channel    = convoca_publisher()->get_channel( $channel_id );
		if ( ! $channel ) {
			wp_die( esc_html__( 'Canal no encontrado.', 'convoca-publisher' ) );
		}
		$result = $channel->verify_connection();

		// El resultado se guarda por canal (no solo como aviso de un momento): es lo
		// que alimenta el estado ✅/⚠️/🔑 de la tarjeta. La huella evita enseñar un
		// error viejo cuando la credencial ya es otra.
		$estados                = (array) get_option( 'convoca_publisher_verify_status', array() );
		$estados[ $channel_id ] = array(
			'success'     => ! empty( $result['success'] ),
			'message'     => (string) $result['message'],
			'time'        => current_time( 'mysql' ),
			'fingerprint' => self::channel_fingerprint( $channel ),
		);

		update_option( 'convoca_publisher_verify_status', $estados, false );

		set_transient( 'convoca_publisher_verify_result_' . get_current_user_id(), $result, 30 );
		wp_safe_redirect( add_query_arg( 'convoca_publisher_verified', $channel_id, wp_get_referer() ) );
		exit;
	}

	/**
	 * Render the moderation queue tab: listado de pendientes con Aprobar/Rechazar.
	 */
	private static function render_moderation_tab(): void {
		Vista_Registro::moderacion();
	}

	/**
	 * Aprobar una publicación pendiente de moderación.
	 */
	public static function handle_approve_review(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		check_admin_referer( 'convoca_publisher_review_' . $id );

		$result = Retry::approve( $id );
		set_transient(
			'convoca_publisher_review_notice',
			array(
				'success' => ! empty( $result['success'] ),
				'message' => ! empty( $result['success'] ) ? __( 'Publicación aprobada y enviada.', 'convoca-publisher' ) : ( $result['error'] ?? __( 'No se pudo publicar.', 'convoca-publisher' ) ),
			),
			30
		);

		wp_safe_redirect( admin_url( 'admin.php?page=convoca-publisher&tab=moderation' ) );
		exit;
	}

	/**
	 * Rechazar una publicación pendiente de moderación.
	 */
	public static function handle_reject_review(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		check_admin_referer( 'convoca_publisher_review_' . $id );

		Retry::reject( $id );
		set_transient(
			'convoca_publisher_review_notice',
			array(
				'success' => true,
				'message' => __( 'Publicación rechazada.', 'convoca-publisher' ),
			),
			30
		);

		wp_safe_redirect( admin_url( 'admin.php?page=convoca-publisher&tab=moderation' ) );
		exit;
	}

	/**
	 * Render the setup guide tab with step-by-step instructions for each channel.
	 */
	private static function render_guide_tab(): void {
		Vista_Guia::render();
	}

	/**
	 * Guardar (o crear) una cuenta.
	 */
	public static function handle_save_account(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}

		check_admin_referer( 'convoca_publisher_save_account' );

		$network_id = isset( $_POST['red'] ) ? sanitize_key( wp_unslash( (string) $_POST['red'] ) ) : '';
		$account_id = isset( $_POST['cuenta'] ) ? sanitize_key( wp_unslash( (string) $_POST['cuenta'] ) ) : '';
		$name       = isset( $_POST['cuenta_nombre'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['cuenta_nombre'] ) ) : '';
		$template   = isset( $_POST['cuenta_plantilla'] ) ? wp_kses_post( wp_unslash( (string) $_POST['cuenta_plantilla'] ) ) : '';
		$networks   = Plugin::networks();

		if ( ! isset( $networks[ $network_id ] ) ) {
			wp_die( esc_html__( 'Esa red no existe.', 'convoca-publisher' ) );
		}

		$settings = array();

		foreach ( array_keys( $networks[ $network_id ]->get_settings_fields() ) as $option ) {
			if ( str_ends_with( $option, '_template' ) || ! isset( $_POST[ $option ] ) ) {
				continue;
			}

			$settings[ $option ] = sanitize_text_field( wp_unslash( (string) $_POST[ $option ] ) );
		}

		if ( '' !== $account_id && Profile_Store::find( $account_id ) ) {
			Profile_Store::update(
				$account_id,
				array(
					'name'     => $name,
					'template' => $template,
					'settings' => $settings,
				)
			);
			$destino = $account_id;
		} else {
			$cuenta = Profile_Store::create( $network_id, $name, $settings, $template );

			if ( false === $cuenta ) {
				wp_safe_redirect( self::tab_url( 'channels' ) );

				return;
			}

			$destino = $cuenta['id'];
		}

		wp_safe_redirect(
			self::tab_url(
				'channels',
				array(
					'canal'    => $destino,
					'guardado' => 1,
				)
			)
		);
		exit;
	}

	/**
	 * Borrar una cuenta.
	 */
	public static function handle_delete_account(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}

		check_admin_referer( 'convoca_publisher_delete_account' );

		$account_id = isset( $_POST['cuenta'] ) ? sanitize_key( wp_unslash( (string) $_POST['cuenta'] ) ) : '';

		Profile_Store::delete( $account_id );

		wp_safe_redirect( self::tab_url( 'channels' ) );
		exit;
	}

	/**
	 * La cola: calendario de lo que va a salir y lista con lo que espera, lo que falló y lo
	 * que ya salió. Se puede reprogramar (o arrastrar a otro día) y quitar de la cola sin
	 * entrar en la entrada.
	 */
	private static function render_queue_tab(): void {
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
				self::tab_url(
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
					self::tab_url(
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
					self::tab_url(
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

	private static function render_queue_week( int $year, int $month, int $day ): void {
		$start = self::queue_week_start( $year, $month, $day );

		$dias = array();

		for ( $i = 0; $i < 7; ++$i ) {
			$dias[] = $start->modify( '+' . $i . ' days' );
		}

		$porDia = self::queue_by_day( $start->getTimestamp(), $start->modify( '+7 days' )->getTimestamp() );

		self::render_queue_grid( $dias, $porDia );
	}

	/**
	 * El lunes de la semana a la que pertenece un día (el día manda: sin él, la vista de
	 * semana no podría avanzar, siempre caería en la semana del día que se le pase).
	 */
	private static function queue_week_start( int $year, int $month, int $day ): \DateTimeImmutable {
		$ref = new \DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, max( 1, min( 31, $day ) ) ), wp_timezone() );

		return $ref->modify( '-' . ( (int) $ref->format( 'N' ) - 1 ) . ' days' )->setTime( 0, 0 );
	}

	private static function queue_month_name( int $year, int $month ): string {
		$fecha = new \DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), wp_timezone() );
		$mes   = wp_date( 'F Y', $fecha->getTimestamp() );

		return false === $mes ? gmdate( 'F Y', $fecha->getTimestamp() ) : $mes;
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

		return self::tab_url(
			'queue',
			array(
				'vista' => $vista,
				'anio'  => (int) $ref->format( 'Y' ),
				'mes'   => (int) $ref->format( 'n' ),
				'dia'   => (int) $ref->format( 'j' ),
			)
		);
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

	/**
	 * Dar salida a mano a lo atrasado (una entrada o todas).
	 */
	public static function handle_queue_send_stuck(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}

		check_admin_referer( 'convoca_publisher_queue_send_stuck' );

		$todos   = ! empty( $_POST['convoca_publisher_todos'] );
		$post_id = isset( $_POST['convoca_publisher_post'] ) ? (int) $_POST['convoca_publisher_post'] : 0;

		self::terminar( Manejador_Cola::forzar_atrasados( $todos, $post_id ) );
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
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
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

	/**
	 * «Compartir ahora» en el listado de entradas, sin abrir el editor: va a las cuentas que
	 * tenga marcadas esa entrada. Para elegir una cuenta concreta, el editor.
	 *
	 * @param array<string, string> $acciones
	 *
	 * @return array<string, string>
	 */
	public static function row_action( array $acciones, \WP_Post $post ): array {
		if ( 'post' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) || array() === Plugin::accounts() ) {
			return $acciones;
		}

		$acciones['convoca_compartir'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::share_now_url( $post->ID ) ),
			esc_html__( 'Compartir ahora', 'convoca-publisher' )
		);

		return $acciones;
	}

	private static function share_now_url( int $post_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=convoca_publisher_share_now&post=' . $post_id ),
			'convoca_publisher_share_now_' . $post_id
		);
	}

	/**
	 * Compartir desde el listado: lanza la publicación y vuelve a la lista.
	 */
	public static function handle_share_now(): void {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}

		check_admin_referer( 'convoca_publisher_share_now_' . $post_id );

		$publicador = Publisher::instance();
		$enviados   = $publicador ? $publicador->publish_to_accounts( $post_id, array(), true, true ) : array();

		set_transient(
			'convoca_publisher_queue_notice_' . get_current_user_id(),
			array() === $enviados
				? __( 'No había ninguna cuenta a la que enviar esta entrada.', 'convoca-publisher' )
				: __( 'Compartido. Mirando la cola, lo tienes.', 'convoca-publisher' ),
			30
		);
		wp_safe_redirect( self::tab_url( 'queue' ) );
		exit;
	}

	/**
	 * Aviso tras compartir desde el listado.
	 */
	public static function shared_notice(): void {
		if ( ! isset( $_GET['convoca_compartido'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Compartido. El detalle está en la cola.', 'convoca-publisher' )
		);
	}

	/**
	 * Reprogramar un envío (desde la lista o arrastrándolo en el calendario).
	 */
	public static function handle_queue_reschedule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}

		check_admin_referer( 'convoca_publisher_queue_reschedule' );

		$ref    = isset( $_POST['convoca_publisher_envio'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_publisher_envio'] ) ) : '';
		$dia    = isset( $_POST['convoca_publisher_dia'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_publisher_dia'] ) ) : '';
		$cuando = isset( $_POST['convoca_publisher_cuando'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_publisher_cuando'] ) ) : '';

		self::terminar( Manejador_Cola::reprogramar( $ref, $dia, $cuando ) );
	}

	/**
	 * Quitar un envío de la cola.
	 */
	public static function handle_queue_cancel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}

		check_admin_referer( 'convoca_publisher_queue_cancel' );

		$ref = isset( $_POST['convoca_publisher_envio'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['convoca_publisher_envio'] ) ) : '';

		self::terminar( Manejador_Cola::cancelar( $ref ) );
	}

	/**
	 * Recolocar ahora mismo, sin esperar al cron.
	 */
	public static function handle_queue_spacing(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-publisher' ) );
		}

		check_admin_referer( 'convoca_publisher_queue_spacing' );

		self::terminar( Manejador_Cola::recolocar() );
	}

	/**
	 * Del formulario a un sello de tiempo del sitio.
	 *
	 * Dos formas de decir cuándo: el día desde el que se suelta un envío en el calendario
	 * (`$dia`, a las 9:00, que es lo que se puede elegir arrastrando) o una fecha y hora
	 * completas (`$cuando`, de la lista de la cola). Todo en la zona horaria del sitio.
	 */
	public static function queue_parse_when( string $dia, string $cuando ): int {
		return Manejador_Cola::parse_when( $dia, $cuando );
	}

	/**
	 * Cierra un manejador: avisa, vuelve a la cola y corta la petición.
	 *
	 * Es el único sitio del bloque que sale con `exit`, y por eso el único que no se puede probar:
	 * lo que se prueba es lo de antes, `Manejador_Cola`, que devuelve el resultado.
	 *
	 * @param array{ok: bool, aviso: string} $resultado Lo que ha pasado.
	 */
	private static function terminar( array $resultado ): void {
		if ( '' !== $resultado['aviso'] ) {
			set_transient( 'convoca_publisher_queue_notice_' . get_current_user_id(), $resultado['aviso'], 30 );
		}

		wp_safe_redirect( self::tab_url( 'queue' ) );
		exit;
	}

	/**
	 * Avisa de las redes que deja fuera la licencia, para que no parezcan un fallo.
	 *
	 * Aplicado el reparto gratis/PRO (issue core#17), cuatro redes no se ofrecen sin licencia. Sin este
	 * aviso, el administrador ve menos canales de los que anuncia la web y no sabe por qué; con él, sabe
	 * qué le falta y dónde se activa.
	 */
	public static function aviso_redes_pro(): void {
		if ( \ConvocaPublisher\Plugin::tiene_licencia_publisher() ) {
			return;
		}

		$pantalla = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $pantalla || false === strpos( (string) $pantalla->id, 'convoca-publisher' ) ) {
			return;
		}

		$nombres = array();
		foreach ( \ConvocaPublisher\Plugin::networks() as $red => $canal ) {
			if ( in_array( $red, \ConvocaPublisher\Plugin::REDES_PRO, true ) ) {
				$nombres[] = $canal->get_name();
			}
		}

		if ( empty( $nombres ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: lista de redes. */
					__( 'Estas redes van con la licencia PRO del Publisher y ahora no se ofrecen: %s. Con la licencia se activan solas.', 'convoca-publisher' ),
					implode( ', ', $nombres )
				)
			)
		);
	}
}
