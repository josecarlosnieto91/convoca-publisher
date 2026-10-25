<?php
/**
 * El arranque de Convoca Publisher: constantes, ganchos y las tres funciones del ciclo de vida.
 *
 * `convoca-publisher.php` estaba al 0 % (68 statements). **No se puede cargar en el proceso de la suite**:
 * el plugin define `ConvocaPublisher\convoca_publisher()`, que en el ámbito del plugin TAPA al doble global
 * del arnés (`tests/stubs.php`) —una llamada sin cualificar cae al global, pero si existe la del namespace
 * gana esa—, y entonces `Queue::accounts_for_post()` deja de ver los canales de mentira y media suite se cae
 * (publisher#13). Por eso esta clase va con **proceso aparte**: el fichero se carga en un hijo y el padre no
 * se entera. Como en PHPUnit 10 los atributos de proceso solo valen en MÉTODOS, van uno por prueba.
 *
 * El grupo `arranque` se EXCLUYE del trabajo de cobertura: con cobertura, PHPUnit no devuelve el objeto desde
 * el proceso hijo y las diez reventarían. Se ejecutan en el trabajo normal de pruebas, que es donde el arranque
 * queda protegido; el 0 % de `convoca-publisher.php` está declarado como excepción en el goal.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[Group( 'arranque' )]
class ArranqueTest extends TestCase {

	/**
	 * Carga el arranque del plugin.
	 *
	 * @return void
	 */
	private function cargar(): void {
		require_once dirname( __DIR__, 2 ) . '/convoca-publisher.php';
	}

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_cp_test_desactivados']     = array();
		$GLOBALS['_cp_test_cleared_hooks']    = array();
		$GLOBALS['_cp_test_activacion']       = null;
		$GLOBALS['_cp_test_desactivacion']    = null;
		$GLOBALS['wp_version']                = '7.0';
		unset( $GLOBALS['_cp_test_wp_die'] );
	}

	/**
	 * Las constantes salen de la cabecera del plugin y del entorno, con guarda para no definirlas dos veces.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_las_constantes_del_arranque(): void {
		$this->cargar();

		$this->assertSame( '8.0', CONVOCA_PUBLISHER_MIN_PHP );
		$this->assertSame( '6.0', CONVOCA_PUBLISHER_MIN_WP );
		$this->assertStringEndsWith( '/', CONVOCA_PUBLISHER_PLUGIN_DIR, 'la ruta lleva barra final: se concatena con nombres de fichero' );
		$this->assertNotSame( '', CONVOCA_PUBLISHER_PLUGIN_URL );
		$this->assertNotSame( '', CONVOCA_PUBLISHER_VERSION, 'la versión es el cache-buster de los assets: no puede quedar vacía' );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+/', CONVOCA_PUBLISHER_VERSION );
	}

	/**
	 * El contenedor se engancha a `plugins_loaded`, no se construye al cargar el fichero.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_el_contenedor_se_engancha_a_plugins_loaded(): void {
		$this->cargar();

		// Se apunta por prioridad: `[10 => [callback, ...]]`.
		$enganchados = array();
		foreach ( (array) ( $GLOBALS['_cp_test_actions']['plugins_loaded'] ?? array() ) as $prioridad => $callbacks ) {
			foreach ( (array) $callbacks as $callback ) {
				$enganchados[] = is_string( $callback ) ? $callback : '';
			}
		}

		$this->assertContains( 'ConvocaPublisher\\convoca_publisher', $enganchados, 'el contenedor espera a que todos los plugins estén cargados' );
	}

	/**
	 * Y devuelve siempre la misma instancia: una sola por petición.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_el_contenedor_es_un_singleton(): void {
		$this->cargar();

		$this->assertSame( \ConvocaPublisher\convoca_publisher(), \ConvocaPublisher\convoca_publisher() );
	}

	/**
	 * Los dos ganchos del ciclo de vida quedan registrados.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_los_ganchos_del_ciclo_de_vida(): void {
		$this->cargar();

		$this->assertIsCallable( $GLOBALS['_cp_test_activacion'] ?? null, 'sin activación no se comprueba el entorno ni se migra nada' );
		$this->assertIsCallable( $GLOBALS['_cp_test_desactivacion'] ?? null, 'sin desactivación quedan los cron programados' );
	}

	/**
	 * Con el entorno correcto, la activación no desactiva nada.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_la_activacion_con_el_entorno_correcto(): void {
		$this->cargar();

		call_user_func( $GLOBALS['_cp_test_activacion'] );

		$this->assertSame( array(), (array) $GLOBALS['_cp_test_desactivados'], 'con PHP y WordPress de sobra no se desactiva' );
		$this->assertArrayNotHasKey( '_cp_test_wp_die', $GLOBALS );
	}

	/**
	 * Con PHP viejo se desactiva y se explica por qué.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_la_activacion_con_php_viejo(): void {
		// Se define ANTES de cargar: el fichero comprueba la constante que encuentra.
		define( 'CONVOCA_PUBLISHER_MIN_PHP', '99.0' );
		$this->cargar();

		try {
			call_user_func( $GLOBALS['_cp_test_activacion'] );
			$this->fail( 'con PHP por debajo del mínimo tiene que cortar' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( '99.0', $e->getMessage(), 'el mensaje dice qué versión hace falta' );
		}

		$this->assertContains( 'convoca-publisher/convoca-publisher.php', (array) $GLOBALS['_cp_test_desactivados'], 'y el plugin se desactiva solo' );
	}

	/**
	 * Con WordPress viejo, lo mismo.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_la_activacion_con_wordpress_viejo(): void {
		$GLOBALS['wp_version'] = '5.0';
		$this->cargar();

		try {
			call_user_func( $GLOBALS['_cp_test_activacion'] );
			$this->fail( 'con WordPress por debajo del mínimo tiene que cortar' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( '6.0', $e->getMessage() );
		}

		$this->assertContains( 'convoca-publisher/convoca-publisher.php', (array) $GLOBALS['_cp_test_desactivados'] );
	}

	/**
	 * Al desactivar se limpian los tres cron que puede haber programados.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_la_desactivacion_limpia_los_cron(): void {
		$this->cargar();

		call_user_func( $GLOBALS['_cp_test_desactivacion'] );

		$limpiados = (array) $GLOBALS['_cp_test_cleared_hooks'];
		sort( $limpiados );

		$this->assertSame(
			array( 'convoca_publisher_retry_event', 'convoca_publisher_retry_failed_posts', 'convoca_publisher_retry_process' ),
			$limpiados,
			'los tres nombres del reintento, ni uno menos'
		);
	}

	/**
	 * El aviso de privacidad dice a dónde van los datos y qué se guarda cifrado.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_el_aviso_de_privacidad(): void {
		$this->cargar();

		ob_start();
		\ConvocaPublisher\convoca_publisher_privacy_notice();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( 'Aviso de privacidad', $salida );
		$this->assertStringContainsString( 'Telegram', $salida, 'nombra las redes a las que se envía' );
		$this->assertStringContainsString( 'cifrados', $salida, 'y que los tokens se guardan cifrados' );
		$this->assertStringNotContainsString( "checked='checked'", $salida, 'sin aceptar, la casilla no sale marcada' );
	}

	/**
	 * Y sale marcada cuando ya se aceptó (si no, el aviso no se podría cerrar nunca).
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_el_aviso_recuerda_que_ya_se_acepto(): void {
		$GLOBALS['_cp_test_options']['convoca_publisher_privacy_acknowledged'] = 1;
		$this->cargar();

		ob_start();
		\ConvocaPublisher\convoca_publisher_privacy_notice();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( "checked='checked'", $salida );
	}
}
