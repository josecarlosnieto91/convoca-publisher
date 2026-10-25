<?php
declare(strict_types=1);

namespace ConvocaPublisher\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Los dobles del arnés copian el contrato real.
 *
 * Un doble que siempre dice que sí es peor que no tener doble: **esconde**. Los tres de este fichero lo hacían
 * (publisher#12):
 *
 * - `add_action()` era un **no-op**: nada quedaba enganchado, así que ninguna prueba podía afirmar qué se registra.
 * - `checked()` devolvía **siempre** `checked='checked'`.
 * - `wp_die()` era un **no-op**: una guarda que en producción corta la petición aquí seguía adelante.
 *
 * @package ConvocaPublisher\Tests
 */
final class DoblesDelArnesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_cp_test_actions'] = array();
		unset( $GLOBALS['_cp_test_wp_die'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_cp_test_actions'], $GLOBALS['_cp_test_wp_die'] );
		parent::tearDown();
	}

	/**
	 * `add_action()` apunta el enganche, con su prioridad.
	 */
	public function test_add_action_apunta_el_enganche(): void {
		$callback = static function (): void {};

		add_action( 'init', $callback );
		add_action( 'init', $callback, 5 );

		$this->assertArrayHasKey( 'init', $GLOBALS['_cp_test_actions'] );
		$this->assertContains( $callback, $GLOBALS['_cp_test_actions']['init'][10], 'prioridad por defecto' );
		$this->assertContains( $callback, $GLOBALS['_cp_test_actions']['init'][5], 'prioridad explícita' );
	}

	/**
	 * `checked()` devuelve el atributo **solo** cuando coinciden.
	 */
	public function test_checked_solo_marca_cuando_coincide(): void {
		$this->assertSame( " checked='checked'", checked( '1', '1' ) );
		$this->assertSame( '', checked( '1', '0' ), 'no coincide, no se marca' );
		$this->assertSame( '', checked( '', '1' ), 'vacío no marca' );
		$this->assertSame( " checked='checked'", checked( true, true ) );
		$this->assertSame( '', checked( false, true ) );
	}

	/**
	 * `checked()` con `$echo` imprime y devuelve lo mismo.
	 */
	public function test_checked_con_echo(): void {
		ob_start();
		$devuelto = checked( '1', '1', true );
		$impreso  = (string) ob_get_clean();

		$this->assertSame( " checked='checked'", $devuelto );
		$this->assertSame( $impreso, $devuelto, 'lo que imprime y lo que devuelve es lo mismo' );
	}

	/**
	 * `wp_die()` **corta**, como en WordPress, y deja dicho por qué.
	 */
	public function test_wp_die_corta_la_peticion(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die: sin permiso' );

		wp_die( 'sin permiso' );
	}

	/**
	 * Y el mensaje queda apuntado para poder afirmarlo desde fuera.
	 */
	public function test_wp_die_apunta_el_mensaje(): void {
		try {
			wp_die( 'nonce inválido' );
			$this->fail( 'wp_die tiene que cortar' );
		} catch ( \RuntimeException $e ) {
			// Se espera.
		}

		$this->assertSame( 'nonce inválido', $GLOBALS['_cp_test_wp_die'] );
	}
}
