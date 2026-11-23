<?php

/**
 * Envío por cuenta: no se repite lo que ya salió y «Compartir ahora» sí puede repetir.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Policy;
use ConvocaPublisher\Publisher;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class PublisherSentTest extends TestCase {

	protected function setUp(): void {
		\cp_test_reset();
		parent::setUp();
	}

	public function test_al_salir_se_apunta_la_cuenta_con_su_permalink(): void {
		$canal     = new FakeChannel( 'telegram-1', 'telegram', 'Telegram' );
		$publisher = new Publisher( array( 'telegram-1' => $canal ) );

		$publisher->publish_post( 5 );

		$enviadas = Publisher::sent_map( 5 );

		$this->assertArrayHasKey( 'telegram-1', $enviadas );
		$this->assertSame( 'ok', $enviadas['telegram-1']['post_id'] );
		$this->assertSame( 'https://example.com/?p=5', $enviadas['telegram-1']['permalink'] );
	}

	public function test_un_envio_normal_no_repite_la_cuenta_que_ya_salio(): void {
		$canal     = new FakeChannel( 'telegram-1', 'telegram', 'Telegram' );
		$publisher = new Publisher( array( 'telegram-1' => $canal ) );

		$publisher->publish_post( 5 );
		$publisher->publish_post( 5 );

		$this->assertCount( 1, $canal->sent, 'el segundo pase no puede volver a publicar' );
	}

	public function test_compartir_a_mano_repite_aunque_ya_haya_salido(): void {
		$canal     = new FakeChannel( 'telegram-1', 'telegram', 'Telegram' );
		$publisher = new Publisher( array( 'telegram-1' => $canal ) );

		$publisher->publish_post( 5 );
		$publisher->publish_to_accounts( 5, array( 'telegram-1' ), true, true );

		$this->assertCount( 2, $canal->sent, '«Compartir ahora» es la válvula de escape' );
	}

	public function test_compartir_a_mano_funciona_en_una_cuenta_manual(): void {
		update_option( Policy::MODE_OPTION, Policy::MODE_QUEUE );
		update_option( Policy::POLICY_OPTION, array( '*' => Policy::MANUAL ) );

		$canal     = new FakeChannel( 'telegram-1', 'telegram', 'Telegram' );
		$publisher = new Publisher( array( 'telegram-1' => $canal ) );

		$publisher->publish_to_accounts( 5, array( 'telegram-1' ), true, true );

		$this->assertCount( 1, $canal->sent, 'manual solo espera a que alguien lo comparta' );
	}

	public function test_una_entrada_publicada_por_una_cuenta_sigue_pudiendo_salir_por_otra(): void {
		$telegram = new FakeChannel( 'telegram-1', 'telegram', 'Telegram' );
		$facebook = new FakeChannel( 'facebook-1', 'facebook', 'Facebook' );

		$GLOBALS['_cp_test_postmeta'][5] = array(
			'_convoca_publisher_published' => true,
			Publisher::SENT_META          => array(
				'facebook-1' => array( 'time' => '2026-11-23 09:00:00', 'post_id' => 'fb', 'permalink' => '' ),
			),
		);

		$publisher = new Publisher(
			array(
				'telegram-1' => $telegram,
				'facebook-1' => $facebook,
			)
		);

		$publisher->publish_post( 5 );

		$this->assertCount( 1, $telegram->sent, 'la cuenta que faltaba recibe la entrada' );
		$this->assertCount( 0, $facebook->sent, 'la que ya la tenía no se repite' );
	}
}
