<?php

/**
 * Modo de publicación y política por cuenta (publisher#20).
 *
 * Lo que se fija aquí son las cuatro combinaciones del migrador y las dos reglas que no se pueden
 * saltar: cambiar de modo no publica lo que estaba en revisión y perder la licencia no publica nada
 * que estuviera esperando aprobación.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Policy;
use PHPUnit\Framework\TestCase;

final class PolicyTest extends TestCase {

	protected function setUp(): void {
		\cp_test_reset();
		parent::setUp();
	}

	/**
	 * Da la licencia PRO durante el caso.
	 */
	private function con_licencia(): void {
		add_filter( 'convoca_publisher_redes_pro', static fn (): bool => true );
	}

	public function test_por_defecto_el_modo_es_auto_y_todo_publica(): void {
		$this->assertSame( Policy::MODE_AUTO, Policy::mode() );
		$this->assertSame( Policy::AUTO, Policy::policy_for( 'telegram' ) );
	}

	public function test_migracion_sin_autopublicacion_deja_todo_manual(): void {
		$mapa = Policy::migration( false, 'off', array() );

		$this->assertSame( Policy::MODE_AUTO, $mapa['mode'] );
		$this->assertSame( Policy::MANUAL, $mapa['policy']['*'], 'nada se publicaba solo; nada debe empezar a hacerlo' );
	}

	public function test_migracion_con_autopublicacion_y_sin_moderacion_es_auto(): void {
		$mapa = Policy::migration( true, 'off', array() );

		$this->assertSame( Policy::MODE_AUTO, $mapa['mode'] );
		$this->assertSame( Policy::AUTO, $mapa['policy']['*'] );
	}

	public function test_migracion_con_moderacion_total_es_cola_con_todo_a_revisar(): void {
		$mapa = Policy::migration( true, 'all', array() );

		$this->assertSame( Policy::MODE_QUEUE, $mapa['mode'] );
		$this->assertSame( Policy::REVIEW, $mapa['policy']['*'] );
	}

	public function test_migracion_por_canal_solo_marca_los_canales_listados(): void {
		$mapa = Policy::migration( true, 'canal', array( 'facebook-centro', 'telegram' ) );

		$this->assertSame( Policy::MODE_QUEUE, $mapa['mode'] );
		$this->assertSame( Policy::AUTO, $mapa['policy']['*'], 'el resto sigue publicando' );
		$this->assertSame( Policy::REVIEW, $mapa['policy']['facebook-centro'] );
		$this->assertSame( Policy::REVIEW, $mapa['policy']['telegram'] );
	}

	public function test_en_modo_auto_lo_marcado_manual_no_publica(): void {
		update_option( Policy::MODE_OPTION, Policy::MODE_AUTO );
		update_option( Policy::POLICY_OPTION, array( '*' => Policy::AUTO, 'telegram' => Policy::MANUAL ) );

		$this->assertSame( Policy::MANUAL, Policy::policy_for( 'telegram' ) );
		$this->assertSame( Policy::AUTO, Policy::policy_for( 'facebook' ) );
	}

	public function test_en_modo_auto_un_review_guardado_cae_a_manual(): void {
		update_option( Policy::MODE_OPTION, Policy::MODE_AUTO );
		update_option( Policy::POLICY_OPTION, array( '*' => Policy::REVIEW ) );

		$this->assertSame( Policy::MANUAL, Policy::policy_for( 'telegram' ), 'cambiar de modo no puede publicar lo que esperaba revisión' );
	}

	public function test_sin_licencia_el_modo_cola_no_encola_y_no_publica(): void {
		update_option( Policy::MODE_OPTION, Policy::MODE_QUEUE );
		update_option( Policy::POLICY_OPTION, array( '*' => Policy::REVIEW ) );

		$this->assertSame( Policy::MANUAL, Policy::policy_for( 'telegram' ) );
	}

	public function test_sin_licencia_lo_automatico_sigue_siendo_automatico(): void {
		update_option( Policy::MODE_OPTION, Policy::MODE_QUEUE );
		update_option( Policy::POLICY_OPTION, array( '*' => Policy::AUTO ) );

		$this->assertSame( Policy::AUTO, Policy::policy_for( 'telegram' ) );
	}

	public function test_con_licencia_el_modo_cola_respeta_el_estado(): void {
		$this->con_licencia();
		update_option( Policy::MODE_OPTION, Policy::MODE_QUEUE );
		update_option( Policy::POLICY_OPTION, array( '*' => Policy::AUTO, 'telegram' => Policy::REVIEW ) );

		$this->assertSame( Policy::REVIEW, Policy::policy_for( 'telegram' ) );
		$this->assertSame( Policy::AUTO, Policy::policy_for( 'facebook' ) );
	}

	public function test_un_mapa_roto_no_rompe_la_decision(): void {
		update_option( Policy::POLICY_OPTION, 'basura' );

		$this->assertSame( Policy::AUTO, Policy::policy_for( 'telegram' ) );
	}

	public function test_un_estado_desconocido_cae_a_auto(): void {
		update_option( Policy::POLICY_OPTION, array( '*' => 'loquesea' ) );

		$this->assertSame( Policy::AUTO, Policy::policy_for( 'telegram' ) );
	}
}
