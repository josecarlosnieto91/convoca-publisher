<?php

/**
 * Los canales de publicación: qué hacen sin credenciales, qué hacen cuando la red responde y qué
 * hacen cuando la red falla.
 *
 * Estaban entre el 26 % y el 42 % de cobertura. No es que no se probaran: se probaban los canales
 * de mentira (`FakeChannel`), que nunca entran en el código real de cada red. Aquí se ejercita el
 * `publish()` de verdad de cada canal contra el doble de HTTP del arnés, con la respuesta buena y
 * con la respuesta mala, que es donde de verdad se rompe una publicación.
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Channels\Facebook;
use ConvocaPublisher\Channels\GoogleMyBusiness;
use ConvocaPublisher\Channels\LinkedIn;
use ConvocaPublisher\Channels\Mastodon;
use ConvocaPublisher\Channels\Telegram;
use ConvocaPublisher\Channels\TikTok;
use ConvocaPublisher\Channels\Twitter;
use PHPUnit\Framework\TestCase;

final class CanalesRealesTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_cp_test_options'] = [];
        $GLOBALS['_cp_test_http_queue'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_cp_test_options'], $GLOBALS['_cp_test_http_queue']);
    }

    /**
     * Credenciales mínimas de cada red: sin ellas el canal ni lo intenta.
     *
     * @return array<string, array<string, string>>
     */
    private function credenciales(): array
    {
        return [
            'linkedin' => ['convoca_publisher_linkedin_token' => 'tok', 'convoca_publisher_linkedin_urn' => 'urn:li:person:1'],
            'googlemybusiness' => ['convoca_publisher_gmb_token' => 'tok', 'convoca_publisher_gmb_location' => 'loc-1'],
            'mastodon' => ['convoca_publisher_mastodon_token' => 'tok', 'convoca_publisher_mastodon_server' => 'https://mastodon.example'],
            'telegram' => ['convoca_publisher_telegram_token' => '123:ABC', 'convoca_publisher_telegram_chat_id' => '-100'],
            'tiktok' => ['convoca_publisher_tiktok_token' => 'tok', 'convoca_publisher_tiktok_open_id' => 'open-1'],
            'twitter' => ['convoca_publisher_twitter_bearer_token' => 'tok'],
            'facebook' => ['convoca_publisher_facebook_token' => 'tok', 'convoca_publisher_facebook_page_id' => 'pag-1'],
        ];
    }

    /**
     * @return array<string, array{0: object}>
     */
    public static function canales(): array
    {
        return [
            'linkedin' => [new LinkedIn()],
            'gmb' => [new GoogleMyBusiness()],
            'mastodon' => [new Mastodon()],
            'telegram' => [new Telegram()],
            'tiktok' => [new TikTok()],
            'twitter' => [new Twitter()],
            'facebook' => [new Facebook()],
        ];
    }

    /**
     * @dataProvider canales
     */
    public function test_sin_credenciales_el_canal_no_esta_disponible(object $canal): void
    {
        $this->assertFalse($canal->is_available(), 'sin credenciales no puede estar disponible');
    }

    /**
     * @dataProvider canales
     */
    public function test_con_credenciales_el_canal_esta_disponible(object $canal): void
    {
        foreach ($this->credenciales()[$canal->get_id()] as $opcion => $valor) {
            $GLOBALS['_cp_test_options'][$opcion] = $valor;
        }

        $this->assertTrue($canal->is_available(), 'con credenciales tiene que estar disponible');
    }

    /**
     * @dataProvider canales
     */
    public function test_con_credenciales_y_red_que_responde_publica(object $canal): void
    {
        foreach ($this->credenciales()[$canal->get_id()] as $opcion => $valor) {
            $GLOBALS['_cp_test_options'][$opcion] = $valor;
        }

        // Respuesta correcta: el doble devuelve esto la primera vez que el canal pregunte.
        // Un cuerpo que vale para todas: cada red lee una clave distinta de la respuesta
        // (Telegram `result.message_id`, TikTok `data.publish_id`, el resto `id`).
        // Cada red lee una clave distinta de la respuesta: Google My Business `name`,
        // Telegram `result.message_id` (con `ok`), X `data.id` y TikTok `data.publish_id`.
        $cuerpo = json_encode([
            'id' => 'publicado-1',
            'post_id' => 'publicado-1',
            'name' => 'accounts/1/locations/2/localPosts/3',
            'ok' => true,
            'result' => ['message_id' => 41],
            'data' => ['id' => 'tweet-1', 'publish_id' => 'publicado-1'],
        ]);
        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 200], 'body' => $cuerpo],
            ['response' => ['code' => 200], 'body' => $cuerpo],
            ['response' => ['code' => 200], 'body' => $cuerpo],
        ];

        $resultado = $canal->publish(10, 'Mensaje de prueba', 'https://example.com/?p=10');

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('success', $resultado, 'todo canal tiene que decir si publicó');
        $this->assertTrue($resultado['success'], 'con 200 la publicación tiene que darse por buena');
    }

    /**
     * @dataProvider canales
     */
    /**
     * Google My Business rechaza `UNSPECIFIED_ALERT_TYPE` con `STANDARD` (publisher#22).
     */
    public function test_gmb_no_manda_alert_type_con_una_publicacion_standard(): void
    {
        foreach ($this->credenciales()['googlemybusiness'] as $opcion => $valor) {
            $GLOBALS['_cp_test_options'][$opcion] = $valor;
        }

        $GLOBALS['_cp_test_http'] = [];
        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 200], 'body' => '{"name":"accounts/1/locations/2/localPosts/3"}'],
        ];

        $resultado = (new GoogleMyBusiness())->publish(10, 'Mensaje de prueba', 'https://example.com/?p=10');

        $this->assertTrue($resultado['success'], 'con 200 y name la publicación vale');
        $this->assertNotEmpty($GLOBALS['_cp_test_http'], 'tiene que haber llamado a la API');

        $cuerpo = json_decode((string) $GLOBALS['_cp_test_http'][0]['args']['body'], true);

        $this->assertArrayNotHasKey('alertType', $cuerpo, 'con STANDARD el campo no se manda: la API lo rechaza');
        $this->assertSame('STANDARD', $cuerpo['topicType']);
    }

    /**
     * Con imagen, el anuncio de GMB lleva el medio.
     */
    public function test_gmb_manda_la_imagen_cuando_la_hay(): void
    {
        foreach ($this->credenciales()['googlemybusiness'] as $opcion => $valor) {
            $GLOBALS['_cp_test_options'][$opcion] = $valor;
        }

        $GLOBALS['_cp_test_http'] = [];
        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 200], 'body' => '{"name":"accounts/1/locations/2/localPosts/4"}'],
        ];

        (new GoogleMyBusiness())->publish(10, 'Mensaje', 'https://example.com/?p=10', 'https://example.com/foto.jpg');

        $cuerpo = json_decode((string) $GLOBALS['_cp_test_http'][0]['args']['body'], true);

        $this->assertSame('https://example.com/foto.jpg', $cuerpo['media'][0]['sourceUrl']);
        $this->assertSame('PHOTO', $cuerpo['media'][0]['mediaFormat']);
    }

    /**
     * @dataProvider canales
     */
    public function test_con_credenciales_y_red_que_falla_no_dice_que_publico(object $canal): void
    {
        foreach ($this->credenciales()[$canal->get_id()] as $opcion => $valor) {
            $GLOBALS['_cp_test_options'][$opcion] = $valor;
        }

        // La red contesta 500 (o el doble se queda sin respuestas y devuelve vacío).
        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 500], 'body' => '{"error":"boom"}'],
            ['response' => ['code' => 500], 'body' => '{"error":"boom"}'],
        ];

        $resultado = $canal->publish(10, 'Mensaje de prueba', 'https://example.com/?p=10');

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('success', $resultado);
        $this->assertFalse($resultado['success'], 'si la red falla no puede decir que publicó');
        $this->assertNotEmpty($resultado['error'] ?? '', 'y tiene que decir por qué');
    }
}
