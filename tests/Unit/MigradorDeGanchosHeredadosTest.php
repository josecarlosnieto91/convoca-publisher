<?php

/**
 * La migración que limpia los ganchos de cron heredados, con sus dos negativos.
 *
 * Por qué existe: en un sitio de cliente y en la demo seguía agendado cada hora `convoca_publisher_retry_process`,
 * un nombre que ya no programa ningún fichero del plugin y que nadie atendía. El arreglo limpia al
 * cambiar de versión, así que lo que hay que vigilar es justo eso: que limpie cuando toca, que **no**
 * repita cuando la versión ya está al día, y que ningún gancho de la lista se vuelva a programar.
 *
 * @package Convoca\Publisher
 */

namespace Convoca\Publisher\Tests\Unit;

use ConvocaPublisher\Migrator;
use PHPUnit\Framework\TestCase;

class MigradorDeGanchosHeredadosTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_cp_test_options']       = array();
		$GLOBALS['_cp_test_cleared_hooks'] = array();
		require_once dirname( __DIR__, 2 ) . '/includes/class-migrator.php';
	}

	public function test_limpia_los_ganchos_heredados_al_cambiar_la_version(): void {
		$GLOBALS['_cp_test_options'][ Migrator::VERSION_OPTION ] = '1.0.0';

		Migrator::migrar_ganchos_heredados();

		$this->assertSame(
			Migrator::GANCHOS_HEREDADOS,
			$GLOBALS['_cp_test_cleared_hooks'],
			'La migración tiene que limpiar TODOS los ganchos heredados, no una muestra.'
		);
		$this->assertSame(
			CONVOCA_PUBLISHER_VERSION,
			$GLOBALS['_cp_test_options'][ Migrator::VERSION_OPTION ],
			'La migración tiene que dejar anotada la versión que ha migrado.'
		);
	}

	public function test_no_vuelve_a_limpiar_si_la_version_ya_esta_al_dia(): void {
		$GLOBALS['_cp_test_options'][ Migrator::VERSION_OPTION ] = CONVOCA_PUBLISHER_VERSION;

		Migrator::migrar_ganchos_heredados();

		$this->assertSame(
			array(),
			$GLOBALS['_cp_test_cleared_hooks'],
			'Con la versión al día la migración no debe tocar nada: se ejecuta en cada petición.'
		);

		// Y después de migrar, una segunda pasada tampoco limpia.
		Migrator::migrar_ganchos_heredados();
		$this->assertSame( array(), $GLOBALS['_cp_test_cleared_hooks'] );
	}

	public function test_ningun_gancho_heredado_se_programa_en_el_codigo(): void {
		$programados = array();
		$raiz        = dirname( __DIR__, 2 );
		$iter        = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $raiz ) );

		foreach ( $iter as $fichero ) {
			$ruta = str_replace( '\\', '/', $fichero->getPathname() );
			if ( 'php' !== strtolower( $fichero->getExtension() ) ) {
				continue;
			}
			if ( false !== strpos( $ruta, '/vendor/' ) || false !== strpos( $ruta, '/tests/' ) || false !== strpos( $ruta, '/node_modules/' ) ) {
				continue;
			}

			$contenido = (string) file_get_contents( $ruta );
			if ( ! preg_match_all( '/wp_schedule_(?:event|single_event)\s*\((.*?)\)\s*;/s', $contenido, $llamadas ) ) {
				continue;
			}

			foreach ( $llamadas[1] as $args ) {
				// El gancho es el ÚLTIMO literal entrecomillado: los de delante son la marca de tiempo
				// y la recurrencia, que también puede empezar por 'convoca_'. Se aceptan las dos
				// comillas: con solo las simples, una reintroducción con comillas dobles pasaba
				// desapercibida (probado en negativo).
				if ( preg_match_all( '/[\'"]([a-z0-9_]+)[\'"]/i', $args, $literales ) && ! empty( $literales[1] ) ) {
					$programados[ end( $literales[1] ) ] = true;
				}
			}
		}

		$reintroducidos = array_values( array_intersect( Migrator::GANCHOS_HEREDADOS, array_keys( $programados ) ) );

		$this->assertSame(
			array(),
			$reintroducidos,
			'Estos ganchos están en la lista de heredados y el código los vuelve a programar: '
				. implode( ', ', $reintroducidos )
				. '. Si el nombre es el bueno, quítalos de GANCHOS_HEREDADOS; si no, la migración los estaría borrando.'
		);
	}
}
