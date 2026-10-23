<?php

/**
 * Telegram, el canal que quedaba por debajo del 80 %: su verificación de conexión pregunta a
 * `getMe` y confía en el campo `ok` de la respuesta, no en el código HTTP (Telegram contesta 200
 * también cuando el token es malo). Eso es justo lo que hay que dejar afirmado.
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Channels\Telegram;
use PHPUnit\Framework\TestCase;

final class TelegramTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_cp_test_options'] = [];
        $GLOBALS['_cp_test_http_queue'] = [];
        $GLOBALS['_cp_test_http'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_cp_test_options'], $GLOBALS['_cp_test_http_queue'], $GLOBALS['_cp_test_http']);
    }

    private function configurado(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_telegram_token'] = 'token-de-prueba-sin-formato-real';
        $GLOBALS['_cp_test_options']['convoca_publisher_telegram_chat_id'] = '-1001234567890';
    }

    public function test_verificar_conexion_sin_configurar(): void
    {
        $resultado = (new Telegram())->verify_connection();

        $this->assertFalse($resultado['success']);
        $this->assertEmpty($GLOBALS['_cp_test_http'], 'sin token no se llama a Telegram');
    }

    public function test_verificar_conexion_correcta_dice_el_bot(): void
    {
        $this->configurado();
        $GLOBALS['_cp_test_http_queue'] = [[
            'response' => ['code' => 200],
            'body' => json_encode(['ok' => true, 'result' => ['id' => 1, 'username' => 'convoca_bot']]),
        ]];

        $resultado = (new Telegram())->verify_connection();

        $this->assertTrue($resultado['success']);
        $this->assertStringContainsString('convoca_bot', $resultado['message']);
        $this->assertStringContainsString('/getMe', $GLOBALS['_cp_test_http'][0]['url'], 'la comprobación es getMe');
    }

    public function test_un_200_con_ok_false_no_es_conexion_buena(): void
    {
        $this->configurado();
        // Telegram contesta 200 con `ok: false` cuando el token no vale: mirar solo el código
        // HTTP daría por buena una credencial muerta.
        $GLOBALS['_cp_test_http_queue'] = [[
            'response' => ['code' => 200],
            'body' => json_encode(['ok' => false, 'description' => 'Unauthorized']),
        ]];

        $resultado = (new Telegram())->verify_connection();

        $this->assertFalse($resultado['success'], 'ok:false no puede pasar por conexión buena');
        $this->assertSame('Unauthorized', $resultado['message']);
    }

    public function test_publicar_sin_chat_id_no_lo_intenta(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_telegram_token'] = 'token-de-prueba-sin-formato-real';

        $resultado = (new Telegram())->publish(10, 'Hola', '');

        $this->assertFalse($resultado['success']);
        $this->assertEmpty($GLOBALS['_cp_test_http']);
    }

    public function test_publicar_con_ok_false_explica_el_motivo(): void
    {
        $this->configurado();
        $GLOBALS['_cp_test_http_queue'] = [[
            'response' => ['code' => 200],
            'body' => json_encode(['ok' => false, 'description' => 'chat not found']),
        ]];

        $resultado = (new Telegram())->publish(10, 'Hola', 'https://example.com/?p=10');

        $this->assertFalse($resultado['success']);
        $this->assertSame('chat not found', $resultado['error']);
    }

    public function test_la_validacion_exige_token_y_chat(): void
    {
        $canal = new Telegram();

        $this->assertNotEmpty($canal->validate_settings([]));
        $this->assertSame([], $canal->validate_settings([
            'convoca_publisher_telegram_token' => 'token-de-prueba',
            'convoca_publisher_telegram_chat_id' => '-100',
        ]));
    }
}
