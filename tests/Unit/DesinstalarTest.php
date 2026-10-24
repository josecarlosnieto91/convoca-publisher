<?php
/**
 * Desinstalar: o se borra todo, o no se borra nada. En medio no hay nada bueno.
 *
 * Media desinstalación es lo peor que puede pasar aquí: tokens cifrados que se quedan en la base, o ajustes borrados de un
 * plugin que sigue instalado. Lo que se prueba: con la guarda de «conservar datos» no se toca nada; sin ella se borran
 * las opciones, la tabla de reintentos y **los siete** cron, y sin `WP_UNINSTALL_PLUGIN` el fichero no hace nada.
 *
 * @package Convoca\Publisher\Tests
 */

namespace Convoca\Publisher\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Desinstalación.
 */
final class DesinstalarTest extends TestCase {

	/**
	 * Opciones y cron previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'opciones' => $GLOBALS['_cp_test_options'] ?? array(),
			'cron'     => $GLOBALS['_cp_test_cleared_hooks'] ?? array(),
			'borradas' => $GLOBALS['_pub_borradas'] ?? array(),
			'col'      => $GLOBALS['__pub_col'] ?? null,
		);
		$GLOBALS['_cp_test_options'] = array();
		$GLOBALS['_cp_test_cleared_hooks'] = array();
		$GLOBALS['_cp_test_db']['queries'] = array();
		unset( $GLOBALS['__pub_col'] );
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_cp_test_options'] = $this->previo['opciones'];
		$GLOBALS['_cp_test_cleared_hooks'] = $this->previo['cron'];
		$GLOBALS['_pub_borradas']         = $this->previo['borradas'];
		if ( null === $this->previo['col'] ) {
			unset( $GLOBALS['__pub_col'] );
		} else {
			$GLOBALS['__pub_col'] = $this->previo['col'];
		}
	}

	/**
	 * Ejecuta el desinstalador.
	 */
	private function desinstalar(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'convoca-publisher/convoca-publisher.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	/**
	 * Con `convoca_uninstall_keep_data` a 1 no se borra **nada**.
	 */
	public function test_conservar_los_datos(): void {
		$GLOBALS['_cp_test_options']['convoca_uninstall_keep_data'] = 1;
		$GLOBALS['_cp_test_options']['convoca_publisher_version']  = '1.0.0';

		$this->desinstalar();

		$this->assertArrayHasKey( 'convoca_publisher_version', $GLOBALS['_cp_test_options'], 'no se borra ninguna opción: los ajustes siguen ahí' );
		$this->assertSame( array(), (array) ( $GLOBALS['_cp_test_cleared_hooks'] ?? array() ), 'ni se toca el cron' );
	}

	/**
	 * Sin la guarda: opciones del plugin borradas, tabla fuera y **los siete** cron limpios.
	 */
	public function test_borrar_todo(): void {
		$GLOBALS['_cp_test_options']['convoca_uninstall_keep_data'] = 0;
		$GLOBALS['__pub_col'] = array( 'convoca_publisher_version', 'convoca_publisher_facebook_token' );

		$this->desinstalar();

		$this->assertArrayNotHasKey( 'convoca_publisher_version', $GLOBALS['_cp_test_options'] );
		$this->assertArrayNotHasKey( 'convoca_publisher_facebook_token', $GLOBALS['_cp_test_options'], 'un token no puede quedarse en la base' );
		$consultas = implode( "\n", $GLOBALS['_cp_test_db']['queries'] ?? array() );
		$this->assertStringContainsString( 'convoca_publisher_retry_queue', $consultas, 'la tabla de reintentos se dropea' );

		$cron = $GLOBALS['_cp_test_cleared_hooks'] ?? array();
		foreach ( array(
			'convoca_publisher_retry_failed_posts',
			'convoca_publisher_retry_process',
			'convoca_publisher_scheduled_publish',
			'convoca_publisher_apply_spacing',
			'convoca_publisher_retry_event',
			'convoca_publisher_async_publish',
		) as $hook ) {
			$this->assertContains( $hook, $cron, 'quedaba en el cron tras desinstalar: ' . $hook );
		}
	}

	/**
	 * Sin `WP_UNINSTALL_PLUGIN` el fichero no borra nada: se ejecuta en un proceso aparte para no matar la suite con
	 * su `exit`.
	 */
	public function test_sin_wp_uninstall_plugin_no_hace_nada(): void {
		$fichero = dirname( __DIR__, 2 ) . '/uninstall.php';
		$salida  = shell_exec( 'php -r ' . escapeshellarg( 'include ' . var_export( $fichero, true ) . '; echo "SEGUIA";' ) . ' 2>&1' );

		$this->assertIsString( (string) $salida );
		$this->assertStringNotContainsString( 'SEGUIA', (string) $salida, 'sin la constante tiene que cortar antes de tocar nada' );
	}
}
