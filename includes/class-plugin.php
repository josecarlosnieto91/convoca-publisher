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

// @phpstan-ignore-next-line (constant resolves to local path)
// Classes auto-loaded via Composer classmap. Run `composer dump-autoload --optimize` after adding new files.

class Plugin {

	private array $channels = array();

	public function __construct() {
		$this->load_channels();
		Admin::init();
		Publisher::init( $this->channels );
		Scheduler::init();
		Retry::init();
		Metabox::init();
		Notifications::init();
		Dashboard::init();
		Rest::init();
		Migrator::init();

		// Cifrado automático de tokens (hooks cp_*)
		$this->register_crypto_hooks();
	}

	/**
	 * Registrar hooks de cifrado para todas las opciones de token.
	 */
	private function register_crypto_hooks(): void {
		$token_options = array(
			'convoca_publisher_facebook_token',
			'convoca_publisher_linkedin_token',
			'convoca_publisher_twitter_bearer_token',
			'convoca_publisher_tiktok_token',
			'convoca_publisher_gmb_token',
			'convoca_publisher_telegram_token',
			'convoca_publisher_mastodon_token',
		);

		add_action( 'pre_update_option', array( Crypto::class, 'encrypt_on_save' ), 10, 2 );

		foreach ( $token_options as $option ) {
			add_filter( "option_{$option}", array( Crypto::class, 'decrypt_on_load' ), 10, 2 );
		}
	}

	private function load_channels(): void {
		// `channels` son las **cuentas** (perfiles): lo que usa el motor para publicar.
		$this->channels = self::solo_con_licencia( self::accounts() );
	}

	/**
	 * Redes del plan gratuito, tal y como las anuncia la web de producto.
	 */
	public const REDES_GRATIS = array( 'facebook', 'twitter', 'linkedin' );

	/**
	 * Redes que se venden con licencia PRO (el módulo suelto, 39 €/año).
	 */
	public const REDES_PRO = array( 'googlemybusiness', 'mastodon', 'telegram', 'tiktok' );

	/**
	 * Deja pasar solo las redes que la licencia permite.
	 *
	 * La web vende «3 redes gratis (Facebook, X, LinkedIn) y 7 con PRO» y el plugin **no consultaba la
	 * licencia en ningún sitio**: los ocho canales publicaban siempre, con licencia o sin ella (issue
	 * core#17). El reparto se aplica aquí, que es el único sitio por el que pasan el editor, la cola, el
	 * REST y el historial, así que no queda una puerta que se olvide.
	 *
	 * Si el núcleo no está instalado no hay forma de validar una licencia, así que se aplica el mismo
	 * reparto (fail-closed) y el filtro `convoca_publisher_redes_pro` queda para quien la gestione por
	 * otra vía.
	 *
	 * @param array<string, object> $cuentas Cuentas configuradas (necesita `get_channel_id()`).
	 * @return array<string, object>
	 */
	public static function solo_con_licencia( array $cuentas ): array {
		if ( self::tiene_licencia_publisher() ) {
			return $cuentas;
		}

		foreach ( $cuentas as $id => $cuenta ) {
			// Lo único que hace falta saber es de qué red es la cuenta. Se comprueba el método porque
			// este método es estático y público: con un llamador equivocado, mejor dejar el canal fuera
			// que reventar la pantalla.
			$red = method_exists( $cuenta, 'get_channel_id' ) ? $cuenta->get_channel_id() : (string) $id;
			if ( in_array( $red, self::REDES_PRO, true ) ) {
				unset( $cuentas[ $id ] );
			}
		}

		return $cuentas;
	}

	/**
	 * ¿Tiene este sitio licencia PRO del Publisher?
	 *
	 * @return bool
	 */
	public static function tiene_licencia_publisher(): bool {
		$con_licencia = class_exists( '\Convoca\Core\License_Manager' )
			? \Convoca\Core\License_Manager::has_pro( 'publisher' )
			: false;

		/**
		 * Permite declarar la licencia por otra vía, o repartir de otra manera.
		 *
		 * @param bool $con_licencia Si el sitio tiene licencia PRO del Publisher.
		 */
		return (bool) apply_filters( 'convoca_publisher_redes_pro', $con_licencia );
	}

	/**
	 * Nombres de clase de los canales disponibles.
	 *
	 * El nombre se deriva del fichero respetando su mayúscula real (la misma regla
	 * que usa el classmap de Composer) y, además, se recogen las clases del
	 * namespace que ya estén declaradas. **Nunca se adivina en minúsculas**: ese
	 * fallo dejó al plugin con cero canales, sin poder publicar ni configurar nada.
	 *
	 * @return string[]
	 */
	public static function channel_class_names(): array {
		$names = array();

		foreach ( glob( CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-*.php' ) ?: array() as $file ) {
			$base  = str_replace( array( 'class-', '.php' ), '', basename( $file ) );
			$words = str_replace( '-', ' ', $base );

			$names[] = __NAMESPACE__ . '\\Channels\\' . str_replace( ' ', '', ucwords( $words ) );
		}

		// Red de seguridad: si un canal ya está declarado (lo cargó el autoloader u
		// otro camino), cuenta igual aunque su nombre no salga del fichero.
		foreach ( get_declared_classes() as $class ) {
			if ( str_starts_with( $class, __NAMESPACE__ . '\\Channels\\' ) ) {
				// Solo valen las clases declaradas por un fichero de canales del plugin: una
				// clase anónima o un doble de pruebas en este namespace no es un canal (y
				// `new` sobre ella reventaría). Aquí solo se listan nombres: quien decide
				// si una clase sirve como canal es `discover_channels()`.
				$reflection = new \ReflectionClass( $class );

				$file = (string) $reflection->getFileName();

				if ( ! str_starts_with( $file, CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/' ) ) {
					continue;
				}

				$names[] = $class;
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Clase declarada en el fichero que corresponde a un nombre esperado.
	 *
	 * Es la red que evita depender del classmap: si el nombre derivado no existe,
	 * se incluye el fichero y se devuelve la clase que declare (o cadena vacía).
	 *
	 * @param string $expected Nombre de clase esperado.
	 */
	private static function class_from_file( string $expected ): string {
		$short = substr( $expected, (int) strrpos( $expected, '\\' ) + 1 );
		$file  = CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/class-' . strtolower( $short ) . '.php';

		if ( ! file_exists( $file ) ) {
			return '';
		}

		$before = get_declared_classes();
		require_once $file;

		foreach ( array_diff( get_declared_classes(), $before ) as $declared ) {
			if ( is_subclass_of( $declared, Channels\ChannelInterface::class ) ) {
				return $declared;
			}
		}

		return '';
	}

	/**
	 * Instancias de todos los canales disponibles.
	 *
	 * @return array<string, Channels\ChannelInterface>
	 */
	public static function discover_channels(): array {
		$channels = array();

		foreach ( self::channel_class_names() as $class ) {
			if ( ! class_exists( $class ) ) {
				// El classmap se quedó atrás (un canal nuevo sin `composer dump-autoload`):
				// se carga su fichero y se sigue con la clase declarada.
				$class = self::class_from_file( $class );
			}

			if ( '' === $class || ! is_subclass_of( $class, Channels\ChannelInterface::class ) ) {
				continue;
			}

			$reflection = new \ReflectionClass( $class );
			// Ni una base abstracta ni una clase anónima se pueden instanciar como canal, y
			// aquí es justo donde se instancian: se descartan en este punto.
			if ( $reflection->isAbstract() || $reflection->isAnonymous() ) {
				continue;
			}

			$channel                        = $reflection->newInstance();
			$channels[ $channel->get_id() ] = $channel;
		}

		return $channels;
	}

	public function get_channels(): array {
		return $this->channels;
	}

	/**
	 * Las redes disponibles, sin cuentas de por medio.
	 *
	 * Se usa para lo que se configura por red (la pantalla de alta de una cuenta, la guía,
	 * los campos que define cada canal). Para publicar y para el editor van las **cuentas**
	 * (`get_channels()`): es lo que de verdad se usa para enviar.
	 *
	 * @return array<string, Channels\ChannelInterface>
	 */
	public static function networks(): array {
		return self::discover_channels();
	}

	/**
	 * Construir las cuentas (perfiles) a partir de la configuración guardada.
	 *
	 * Si todavía no hay ninguna y la instalación venía de «un token por red», aquí se
	 * migra: un perfil por red con el id de la red, para no romper lo ya publicado, la
	 * cola ni el historial.
	 *
	 * @return array<string, Channel_Profile>
	 */
	public static function accounts(): array {
		$networks = self::discover_channels();

		Profile_Store::migrate( $networks );

		$accounts = array();

		foreach ( Profile_Store::all() as $profile ) {
			if ( ! isset( $networks[ $profile['channel'] ] ) ) {
				continue; // Una cuenta cuya red ya no existe (canal retirado): se ignora.
			}

			$accounts[ $profile['id'] ] = new Channel_Profile( $profile, $networks[ $profile['channel'] ] );
		}

		return $accounts;
	}

	/**
	 * Get a channel instance by its ID.
	 *
	 * Acepta el id de una **cuenta** (lo normal) o el de una **red** (para la pantalla de
	 * alta de una cuenta nueva, que todavía no existe).
	 *
	 * @param string $id The channel ID (e.g., 'facebook', 'telegram').
	 * @return Channels\ChannelInterface|null
	 */
	public function get_channel( string $id ): ?Channels\ChannelInterface {
		if ( isset( $this->channels[ $id ] ) ) {
			return $this->channels[ $id ];
		}

		$networks = self::networks();

		return $networks[ $id ] ?? null;
	}
}
