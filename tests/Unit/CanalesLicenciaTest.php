<?php

/**
 * El reparto gratis/PRO de las redes del Publisher (issue core#17).
 *
 * La web vende «3 redes gratis (Facebook, X, LinkedIn) y 7 con PRO», y el plugin no consultaba la licencia
 * en ningún sitio: los ocho canales publicaban siempre, con licencia o sin ella. Aquí se fija el reparto,
 * incluida la red que va dentro de otra (Instagram se publica por el canal de Facebook), y que sin núcleo
 * instalado —donde no hay forma de validar nada— no se abren las PRO.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Plugin;
use PHPUnit\Framework\TestCase;

/** Cuenta mínima: lo único que el reparto necesita saber es de qué red es. */
final class CuentaDeRed {

	public function __construct( private string $red ) {}

	public function get_channel_id(): string {
		return $this->red;
	}
}

final class CanalesLicenciaTest extends TestCase {

	/**
	 * Siete redes, con dos cuentas de Facebook (una es Instagram).
	 *
	 * @return array<string, CuentaDeRed>
	 */
	private function cuentas(): array {
		$cuentas = array();
		foreach ( array( 'facebook', 'twitter', 'linkedin', 'googlemybusiness', 'mastodon', 'telegram', 'tiktok' ) as $red ) {
			$cuentas[ $red ] = new CuentaDeRed( $red );
		}
		$cuentas['facebook-2'] = new CuentaDeRed( 'facebook' );

		return $cuentas;
	}

	public function test_sin_licencia_solo_quedan_las_tres_del_plan_gratuito(): void {
		$sin_licencia = static function (): bool {
			return false;
		};
		add_filter( 'convoca_publisher_redes_pro', $sin_licencia );
		$quedan = Plugin::solo_con_licencia( $this->cuentas() );
		remove_filter( 'convoca_publisher_redes_pro', $sin_licencia );

		// El orden no es el asunto: lo que se fija es el conjunto.
		$quedan = array_keys( $quedan );
		sort( $quedan );
		$esperadas = array( 'facebook', 'facebook-2', 'linkedin', 'twitter' );
		sort( $esperadas );

		$this->assertSame( $esperadas, $quedan, 'Sin licencia tienen que quedar Facebook (con Instagram), LinkedIn y X' );
	}

	public function test_con_licencia_pasan_todas(): void {
		$con_licencia = static function (): bool {
			return true;
		};
		add_filter( 'convoca_publisher_redes_pro', $con_licencia );
		$quedan = Plugin::solo_con_licencia( $this->cuentas() );
		remove_filter( 'convoca_publisher_redes_pro', $con_licencia );

		$this->assertCount( 8, $quedan );
	}

	public function test_las_pro_son_las_cuatro_que_no_anuncia_como_gratis(): void {
		$this->assertSame( array( 'googlemybusiness', 'mastodon', 'telegram', 'tiktok' ), Plugin::REDES_PRO );
		$this->assertSame( array( 'facebook', 'twitter', 'linkedin' ), Plugin::REDES_GRATIS );
	}
}
