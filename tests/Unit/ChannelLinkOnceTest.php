<?php

/**
 * El enlace no se pega dos veces.
 *
 * Visto en producción, con el último post de Lugg: Telegram añadía el enlace al final **siempre**,
 * así que con una plantilla que ya trae `{url}` el mismo enlace salía dos veces en el mensaje.
 * Lo mismo hacían Mastodon y Twitter.
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Channels\Telegram;
use PHPUnit\Framework\TestCase;

final class ChannelLinkOnceTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();

        $GLOBALS['_cp_test_options']['convoca_publisher_telegram_token'] = 'token-de-prueba';
        $GLOBALS['_cp_test_options']['convoca_publisher_telegram_chat_id'] = '123';
    }

    /** Lo que el canal pidió de verdad a la API de Telegram. */
    private function textoEnviado(): string
    {
        foreach ($GLOBALS['_cp_test_http'] as $llamada) {
            if (false !== strpos((string) ($llamada['url'] ?? ''), 'sendMessage') || false !== strpos((string) ($llamada['url'] ?? ''), 'sendPhoto')) {
                return (string) ($llamada['args']['body']['text'] ?? $llamada['args']['body']['caption'] ?? '');
            }
        }

        return '';
    }

    public function testConElEnlaceEnLaPlantillaNoSeRepiteAlFinal(): void
    {
        $canal = new Telegram();
        $url   = 'https://lugg.biodevas.org/2026/09/puertas-abiertas/';

        $canal->publish(12167, "Un mensaje\n\n" . $url, $url);

        $enviado = $this->textoEnviado();
        $this->assertSame(1, substr_count($enviado, $url), 'El enlace sale una sola vez, no dos: ' . $enviado);
    }

    public function testSiLaPlantillaNoLlevaEnlaceSeAnade(): void
    {
        $canal = new Telegram();
        $url   = 'https://lugg.biodevas.org/2026/09/puertas-abiertas/';

        $canal->publish(12167, 'Un mensaje sin enlace', $url);

        $enviado = $this->textoEnviado();
        $this->assertSame(1, substr_count($enviado, $url), 'Si no venía, se añade: ' . $enviado);
    }
}
