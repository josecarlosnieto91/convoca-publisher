<?php

/**
 * Propuestas: publicar no envía, deja una propuesta por cuenta.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Publisher;
use ConvocaPublisher\Retry;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class PublisherProposalTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();
        parent::setUp();

        $GLOBALS['_cp_test_options']  = [];
        $GLOBALS['_cp_test_postmeta'] = [];
        $GLOBALS['_cp_test_posts']    = [5];
        $GLOBALS['_cp_test_titles'][5] = 'Entrada de prueba';
        $GLOBALS['_cp_test_status'][5] = 'publish';
    }

    public function test_publicar_crea_propuesta_y_no_envia(): void
    {
        $canal = new FakeChannel('telegram-1', 'telegram', 'Telegram — Pruebas');
        $publisher = new Publisher(['telegram-1' => $canal]);

        $creadas = $publisher->propose_post(5);

        $this->assertSame(1, $creadas);
        $this->assertSame([], $canal->sent, 'Proponer no puede hablar con la red.');

        $inserts = $GLOBALS['_cp_test_db']['inserts'] ?? [];
        $this->assertCount(1, $inserts);
        $this->assertSame(Retry::STATUS_PROPOSAL, $inserts[0]['data']['status']);
        $this->assertStringContainsString('Entrada de prueba', (string) $inserts[0]['data']['payload']);
    }

    public function test_una_cuenta_que_ya_recibio_la_entrada_no_genera_propuesta(): void
    {
        // El booleano viejo ya no bloquea: lo que manda es la marca por cuenta (publisher#20).
        $GLOBALS['_cp_test_postmeta'][5]['_convoca_publisher_sent'] = [
            'telegram-1' => ['time' => '2026-11-23 09:00:00'],
        ];
        $canal = new FakeChannel('telegram-1', 'telegram');
        $publisher = new Publisher(['telegram-1' => $canal]);

        $this->assertSame(0, $publisher->propose_post(5));
        $this->assertSame([], $GLOBALS['_cp_test_db']['inserts'] ?? []);
    }

    public function test_el_booleano_viejo_no_impide_proponer_en_otra_cuenta(): void
    {
        $GLOBALS['_cp_test_postmeta'][5]['_convoca_publisher_published'] = true;
        $canal = new FakeChannel('telegram-1', 'telegram');
        $publisher = new Publisher(['telegram-1' => $canal]);

        $this->assertSame(1, $publisher->propose_post(5), 'una cuenta sin la entrada sigue pudiendo recibirla');
    }
}
