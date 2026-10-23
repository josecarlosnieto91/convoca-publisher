<?php

/**
 * Twitter/X y Mastodon: los dos canales que iban por detrás (45,6 % y 55,4 %).
 *
 * Lo que se prueba no es que las funciones existan, sino las decisiones que se toman con el texto
 * y con la red: qué se manda cuando el mensaje no cabe en 280 caracteres, que no se pegue el
 * enlace dos veces, que la imagen se suba antes de publicar y que la verificación de credenciales
 * distinga «conectado» de «no conectado» sin mentir en ninguno de los dos sentidos.
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Channels\Mastodon;
use ConvocaPublisher\Channels\Twitter;
use PHPUnit\Framework\TestCase;

final class TwitterYMastodonTest extends TestCase
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

    private function conBearer(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_twitter_bearer_token'] = 'bearer-1';
    }

    private function conMastodon(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_mastodon_token'] = 'tok';
        $GLOBALS['_cp_test_options']['convoca_publisher_mastodon_server'] = 'https://mastodon.example/';
    }

    /**
     * @param array<int, array<string, mixed>> $respuestas
     */
    private function encolar(array $respuestas): void
    {
        $GLOBALS['_cp_test_http_queue'] = $respuestas;
    }

    // ── Twitter / X -------------------------------------------------------------------------

    public function test_twitter_sin_token_no_publica_y_lo_dice(): void
    {
        $resultado = (new Twitter())->publish(10, 'Hola', 'https://example.com/?p=10');

        $this->assertFalse($resultado['success']);
        $this->assertArrayHasKey('error', $resultado);
    }

    public function test_twitter_publica_y_devuelve_el_id_del_tweet(): void
    {
        $this->conBearer();
        $this->encolar([['response' => ['code' => 200], 'body' => '{"data":{"id":"tweet-9"}}']]);

        $resultado = (new Twitter())->publish(10, 'Hola mundo', 'https://example.com/?p=10');

        $this->assertTrue($resultado['success']);
        $this->assertSame('tweet-9', $resultado['post_id']);
    }

    public function test_twitter_recorta_lo_que_no_cabe_en_280(): void
    {
        $this->conBearer();
        $this->encolar([['response' => ['code' => 200], 'body' => '{"data":{"id":"t"}}']]);

        $resultado = (new Twitter())->publish(10, str_repeat('palabra ', 60), '');
        $this->assertTrue($resultado['success']);

        // El cuerpo que se envió de verdad: se mira en la petición, no en la respuesta.
        $peticion = $GLOBALS['_cp_test_http'][0] ?? null;
        $this->assertNotNull($peticion, 'tiene que haber una petición registrada');
        $cuerpo = json_decode($peticion['args']['body'], true);
        $this->assertLessThanOrEqual(280, mb_strlen($cuerpo['text']), 'un tweet no puede pasar de 280');
    }

    public function test_twitter_no_repite_el_enlace_si_el_mensaje_ya_lo_lleva(): void
    {
        $this->conBearer();
        $this->encolar([['response' => ['code' => 200], 'body' => '{"data":{"id":"t"}}']]);

        (new Twitter())->publish(10, 'Mira https://example.com/?p=10', 'https://example.com/?p=10');

        $cuerpo = json_decode($GLOBALS['_cp_test_http'][0]['args']['body'], true);
        $this->assertSame(1, substr_count($cuerpo['text'], 'https://example.com/?p=10'), 'el enlace va una sola vez');
    }

    public function test_twitter_sube_la_imagen_antes_de_publicar(): void
    {
        $this->conBearer();
        $this->encolar([
            ['response' => ['code' => 200], 'body' => 'binario-de-la-imagen'], // GET de la imagen
            ['response' => ['code' => 200], 'body' => '{"media_id_string":"media-7"}'], // subida
            ['response' => ['code' => 200], 'body' => '{"data":{"id":"t"}}'], // tweet
        ]);

        $resultado = (new Twitter())->publish(10, 'Con foto', 'https://example.com/?p=10', 'https://example.com/foto.jpg');

        $this->assertTrue($resultado['success']);
        $urls = array_column($GLOBALS['_cp_test_http'], 'url');
        $this->assertStringContainsString('upload.twitter.com', $urls[1], 'la imagen se sube antes');
        $cuerpo = json_decode($GLOBALS['_cp_test_http'][2]['args']['body'], true);
        $this->assertSame(['media-7'], $cuerpo['media']['media_ids'], 'el tweet lleva el media_id de la subida');
    }

    public function test_twitter_si_la_imagen_falla_publica_sin_media(): void
    {
        $this->conBearer();
        $this->encolar([
            ['response' => ['code' => 404], 'body' => ''], // la imagen no está
            ['response' => ['code' => 200], 'body' => '{"data":{"id":"t"}}'],
        ]);

        $resultado = (new Twitter())->publish(10, 'Sin foto', 'https://example.com/?p=10', 'https://example.com/nada.jpg');

        $this->assertTrue($resultado['success'], 'sin imagen también se publica');
        $cuerpo = json_decode($GLOBALS['_cp_test_http'][1]['args']['body'], true);
        $this->assertArrayNotHasKey('media', $cuerpo);
    }

    public function test_twitter_con_401_explica_el_fallo_de_la_red(): void
    {
        $this->conBearer();
        $this->encolar([['response' => ['code' => 401], 'body' => '{"title":"Unauthorized","detail":"token caducado"}']]);

        $resultado = (new Twitter())->publish(10, 'Hola', '');

        $this->assertFalse($resultado['success']);
        $this->assertSame('Unauthorized', $resultado['error'], 'el motivo que da la red no se tapa');
    }

    public function test_twitter_verificar_conexion_sin_token(): void
    {
        $resultado = (new Twitter())->verify_connection();

        $this->assertFalse($resultado['success']);
        $this->assertNotEmpty($resultado['message']);
    }

    public function test_twitter_verificar_conexion_correcta_dice_el_usuario(): void
    {
        $this->conBearer();
        $this->encolar([['response' => ['code' => 200], 'body' => '{"data":{"id":"1","username":"convoca"}}']]);

        $resultado = (new Twitter())->verify_connection();

        $this->assertTrue($resultado['success']);
        $this->assertStringContainsString('@convoca', $resultado['message']);
    }

    public function test_twitter_verificar_conexion_rechazada_no_dice_que_va(): void
    {
        $this->conBearer();
        $this->encolar([['response' => ['code' => 403], 'body' => '{"title":"Forbidden"}']]);

        $resultado = (new Twitter())->verify_connection();

        $this->assertFalse($resultado['success']);
        $this->assertSame('Forbidden', $resultado['message']);
    }

    // ── Mastodon ----------------------------------------------------------------------------

    public function test_mastodon_sin_credenciales_no_publica(): void
    {
        $resultado = (new Mastodon())->publish(10, 'Hola', 'https://example.com/?p=10');

        $this->assertFalse($resultado['success']);
    }

    public function test_mastodon_publica_y_devuelve_id_y_url(): void
    {
        $this->conMastodon();
        $this->encolar([['response' => ['code' => 200], 'body' => '{"id":"m-1","url":"https://mastodon.example/@yo/m-1"}']]);

        $resultado = (new Mastodon())->publish(10, 'Hola', 'https://example.com/?p=10');

        $this->assertTrue($resultado['success']);
        $this->assertSame('m-1', $resultado['post_id']);
        $this->assertSame('https://mastodon.example/@yo/m-1', $resultado['url']);
        // La barra final del servidor se quita: si no, la URL saldría con doble barra.
        $this->assertSame('https://mastodon.example/api/v1/statuses', $GLOBALS['_cp_test_http'][0]['url']);
    }

    public function test_mastodon_sube_la_imagen_y_la_adjunta(): void
    {
        $this->conMastodon();
        $this->encolar([
            ['response' => ['code' => 200], 'body' => 'binario'],
            ['response' => ['code' => 200], 'body' => '{"id":"media-3"}'],
            ['response' => ['code' => 200], 'body' => '{"id":"m-2"}'],
        ]);

        $resultado = (new Mastodon())->publish(10, 'Con foto', 'https://example.com/?p=10', 'https://example.com/f.jpg');

        $this->assertTrue($resultado['success']);
        $cuerpo = json_decode($GLOBALS['_cp_test_http'][2]['args']['body'], true);
        $this->assertSame(['media-3'], $cuerpo['media_ids']);
    }

    public function test_mastodon_si_la_red_da_422_no_dice_que_publico(): void
    {
        $this->conMastodon();
        $this->encolar([['response' => ['code' => 422], 'body' => '{"error":"Texto vacío"}']]);

        $resultado = (new Mastodon())->publish(10, '', '');

        $this->assertFalse($resultado['success']);
        $this->assertSame('Texto vacío', $resultado['error']);
    }

    public function test_mastodon_verificar_conexion_sin_datos(): void
    {
        $resultado = (new Mastodon())->verify_connection();

        $this->assertFalse($resultado['success']);
    }

    public function test_mastodon_verificar_conexion_correcta_dice_la_cuenta(): void
    {
        $this->conMastodon();
        $this->encolar([['response' => ['code' => 200], 'body' => '{"id":"1","acct":"yo"}']]);

        $resultado = (new Mastodon())->verify_connection();

        $this->assertTrue($resultado['success']);
        $this->assertStringContainsString('@yo', $resultado['message']);
    }

    public function test_mastodon_verificar_conexion_rechazada(): void
    {
        $this->conMastodon();
        $this->encolar([['response' => ['code' => 401], 'body' => '{"error":"The access token is invalid"}']]);

        $resultado = (new Mastodon())->verify_connection();

        $this->assertFalse($resultado['success']);
        $this->assertSame('The access token is invalid', $resultado['message']);
    }
}
