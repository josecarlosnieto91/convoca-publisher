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

    public function test_un_post_ya_publicado_no_genera_propuesta(): void
    {
        $GLOBALS['_cp_test_postmeta'][5]['_convoca_publisher_published'] = true;
        $canal = new FakeChannel('telegram-1', 'telegram');
        $publisher = new Publisher(['telegram-1' => $canal]);

        $this->assertSame(0, $publisher->propose_post(5));
        $this->assertSame([], $GLOBALS['_cp_test_db']['inserts'] ?? []);
    }
}
