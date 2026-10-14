<?php

/**
 * Migraciones que corren solas al cambiar de versión.
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

/**
 * Limpia lo que dejan atrás las versiones anteriores.
 *
 * Por qué existe: desplegamos copiando ficheros, así que ni el hook de activación ni el de
 * desactivación llegan a ejecutarse nunca. Un gancho de cron que se llame distinto en una versión
 * posterior se queda agendado para siempre, porque WordPress reprograma los eventos recurrentes: en
 * los sitios medidos seguía corriendo cada hora `convoca_publisher_retry_process`, sin callback y sin
 * que ningún fichero del código actual lo programe (medido el 14/10/2026).
 * (Sin nombrar los sitios a propósito: este comentario viaja dentro del plugin publicado y el guardián
 * de higiene del ecosistema prohíbe los datos de clientes en el código. Lo cazó él, no la vista.)
 *
 * La migración va por versión guardada, no por «una vez»: así el día que renombre otro gancho basta
 * con añadirlo a la lista y las instalaciones se limpian solas al actualizar.
 */
class Migrator {

	/**
	 * Opción donde se guarda la última versión que migró.
	 */
	public const VERSION_OPTION = 'convoca_publisher_version';

	/**
	 * Ganchos de cron que dejaron versiones anteriores.
	 *
	 * NO los programa ningún fichero del código actual: el reintento vivo es
	 * `convoca_publisher_retry_event`. Antes de añadir uno aquí, comprobar que el código no lo
	 * programa en ninguna parte (hay una prueba que lo vigila).
	 */
	public const GANCHOS_HEREDADOS = array(
		'convoca_publisher_retry_process',
	);

	/**
	 * Engancha la migración.
	 *
	 * En `init` a propósito: así corre también en el cron y en el front, sin depender de que alguien
	 * entre al escritorio de WordPress.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'migrar_ganchos_heredados' ), 5 );
	}

	/**
	 * Limpia los ganchos heredados cuando la versión del plugin no es la que migró.
	 */
	public static function migrar_ganchos_heredados(): void {
		$actual   = defined( 'CONVOCA_PUBLISHER_VERSION' ) ? (string) CONVOCA_PUBLISHER_VERSION : '';
		$guardada = (string) get_option( self::VERSION_OPTION, '' );

		if ( '' === $actual || $guardada === $actual ) {
			return;
		}

		foreach ( self::GANCHOS_HEREDADOS as $gancho ) {
			wp_clear_scheduled_hook( $gancho );
		}

		update_option( self::VERSION_OPTION, $actual );
	}
}
