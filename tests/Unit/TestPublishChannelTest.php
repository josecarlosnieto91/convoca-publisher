<?php

/**
 * Canal de pruebas: publicar en uno y solo en uno.
 *
 * Lo que se fija aquí: que probar la integración no pueda publicar sin querer en las redes
 * reales, y que un canal que no existe no publique en ninguno (mejor no hacer nada que
 * hacerlo donde no toca).
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Publisher;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class TestPublishChannelTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();

        $_GET     = [];
        $_REQUEST = [];

        $GLOBALS['_cp_test_options'] = [];
    }

    public function testPublicaSoloEnElCanalElegido(): void
    {
        $pruebas = new FakeChannel('tg-pruebas', 'telegram', 'Telegram de pruebas');
        $real    = new FakeChannel('fb-real', 'facebook', 'Facebook — Página');

        Publisher::init(['tg-pruebas' => $pruebas, 'fb-real' => $real]);

        $resultado = Publisher::instance()->publish_test(7, 'tg-pruebas');

        $this->assertTrue($resultado['success'], 'La prueba salió bien.');
        $this->assertCount(1, $pruebas->sent, 'Se mandó al canal de pruebas.');
        $this->assertSame([], $real->sent, 'Y a la red real, nada.');
    }

    public function testUnCanalQueNoExisteNoPublicaEnNinguno(): void
    {
        $real = new FakeChannel('fb-real', 'facebook', 'Facebook — Página');

        Publisher::init(['fb-real' => $real]);

        $resultado = Publisher::instance()->publish_test(7, 'no-existe');

        $this->assertFalse($resultado['success'], 'Se dice que ese canal no está.');
        $this->assertSame([], $real->sent, 'Y no se publica en otro por fallback.');
    }

    public function testLoQueSeMandaLlevaElMensajeDeEsaEntrada(): void
    {
        $GLOBALS['_cp_test_titles'][7] = 'Taller de huerto';
        $pruebas                       = new FakeChannel('tg-pruebas', 'telegram');

        Publisher::init(['tg-pruebas' => $pruebas]);

        Publisher::instance()->publish_test(7, 'tg-pruebas');

        $this->assertStringContainsString('Taller de huerto', $pruebas->sent[0]['message'], 'El mensaje es el de la entrada, con sus variables sustituidas.');
        $this->assertStringContainsString('http', $pruebas->sent[0]['url'], 'Y va su enlace.');
    }
}
