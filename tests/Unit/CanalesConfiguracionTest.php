<?php

/**
 * Los cuatro canales que quedaban por debajo del 80 %: Google My Business, LinkedIn, Telegram y
 * TikTok. Se ataca lo que decide si un canal sirve o no: si está configurado, si la validación de
 * ajustes caza lo que falta, si la verificación de conexión distingue lo bueno de lo malo y si los
 * campos de ajustes existen con el tipo que espera el formulario.
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Channels\GoogleMyBusiness;
use ConvocaPublisher\Channels\LinkedIn;
use ConvocaPublisher\Channels\Telegram;
use ConvocaPublisher\Channels\TikTok;
use PHPUnit\Framework\TestCase;

final class CanalesConfiguracionTest extends TestCase
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

    /**
     * @return array<string, array{0: object, 1: array<string, string>}>
     */
    public static function canales(): array
    {
        return [
            'gmb' => [new GoogleMyBusiness(), [
                'convoca_publisher_gmb_token' => str_repeat('t', 40),
                'convoca_publisher_gmb_location' => 'accounts/1/locations/2',
            ]],
            'linkedin' => [new LinkedIn(), [
                'convoca_publisher_linkedin_token' => 'tok',
                'convoca_publisher_linkedin_urn' => 'urn:li:person:1',
            ]],
            'telegram' => [new Telegram(), [
                'convoca_publisher_telegram_token' => 'token-de-prueba-sin-formato-real',
                'convoca_publisher_telegram_chat_id' => '-1001234567890',
            ]],
            'tiktok' => [new TikTok(), [
                'convoca_publisher_tiktok_token' => str_repeat('t', 40),
                'convoca_publisher_tiktok_open_id' => 'open-id-123456',
            ]],
        ];
    }

    /**
     * @dataProvider canales
     */
    public function test_sin_configurar_el_canal_no_esta_disponible(object $canal): void
    {
        $this->assertFalse($canal->is_available());
    }

    /**
     * @dataProvider canales
     */
    public function test_configurado_el_canal_esta_disponible(object $canal, array $opciones): void
    {
        foreach ($opciones as $clave => $valor) {
            $GLOBALS['_cp_test_options'][$clave] = $valor;
        }

        $this->assertTrue($canal->is_available());
    }

    /**
     * @dataProvider canales
     */
    public function test_la_validacion_de_ajustes_caza_lo_que_falta(object $canal): void
    {
        $errores = $canal->validate_settings([]);

        $this->assertNotEmpty($errores, 'sin datos, la validación tiene que protestar');
        $this->assertIsString($errores[0], 'cada error es un mensaje para la persona que configura');
    }

    /**
     * @dataProvider canales
     */
    public function test_con_los_datos_buenos_la_validacion_pasa(object $canal, array $opciones): void
    {
        $this->assertSame([], $canal->validate_settings($opciones), 'con datos completos no puede haber errores');
    }

    /**
     * @dataProvider canales
     */
    public function test_los_ajustes_declaran_sus_campos(object $canal): void
    {
        $campos = $canal->get_settings_fields();

        $this->assertNotEmpty($campos);
        foreach ($campos as $clave => $campo) {
            $this->assertArrayHasKey('title', $campo, "el campo {$clave} necesita título");
            $this->assertArrayHasKey('type', $campo, "el campo {$clave} necesita tipo");
        }
    }

    /**
     * @dataProvider canales
     */
    public function test_verificar_conexion_sin_datos_no_dice_que_va(object $canal): void
    {
        $resultado = $canal->verify_connection();

        $this->assertFalse($resultado['success'], 'sin credenciales no puede haber conexión');
        $this->assertNotEmpty($resultado['message']);
    }

    /**
     * Google My Business y TikTok comprueban el formato de las credenciales ANTES de llamar a la
     * red: un token de tres letras no es un token, y no se gasta una petición en averiguarlo.
     */
    public function test_gmb_rechaza_un_token_corto_sin_llamar_a_la_red(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_gmb_token'] = 'tok';
        $GLOBALS['_cp_test_options']['convoca_publisher_gmb_location'] = 'accounts/1/locations/2';

        $resultado = (new GoogleMyBusiness())->verify_connection();

        $this->assertFalse($resultado['success']);
        $this->assertSame([], $GLOBALS['_cp_test_http'], 'no se llama a la red con un token imposible');
    }

    public function test_gmb_rechaza_una_location_que_no_es_la_suya(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_gmb_token'] = str_repeat('t', 40);
        $GLOBALS['_cp_test_options']['convoca_publisher_gmb_location'] = 'loc-1';

        $resultado = (new GoogleMyBusiness())->verify_connection();

        $this->assertFalse($resultado['success']);
        $this->assertSame([], $GLOBALS['_cp_test_http'], 'la location tiene que ser accounts/X/locations/Y');
    }

    public function test_tiktok_rechaza_un_open_id_corto(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_tiktok_token'] = str_repeat('t', 40);
        $GLOBALS['_cp_test_options']['convoca_publisher_tiktok_open_id'] = 'open-1';

        $resultado = (new TikTok())->verify_connection();

        $this->assertFalse($resultado['success']);
    }

    /**
     * @dataProvider canales
     */
    public function test_verificar_conexion_con_la_red_respondiendo(object $canal, array $opciones): void
    {
        foreach ($opciones as $clave => $valor) {
            $GLOBALS['_cp_test_options'][$clave] = $valor;
        }

        // Respuesta buena «navaja suiza»: cada red lee una clave distinta para darse por conectada.
        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 200], 'body' => json_encode([
                'id' => '1',
                'sub' => 'linkedin-user-1',
                'name' => 'accounts/1/locations/2',
                'username' => 'convoca',
                'ok' => true,
                'result' => ['id' => 1, 'username' => 'convoca'],
                'data' => ['id' => '1', 'username' => 'convoca', 'display_name' => 'Convoca'],
            ])],
            ['response' => ['code' => 200], 'body' => json_encode(['ok' => true, 'result' => ['id' => 1]])],
        ];

        $resultado = $canal->verify_connection();

        $this->assertTrue($resultado['success'], 'con 200 la conexión tiene que darse por buena');
        $this->assertNotEmpty($resultado['message']);
    }

    /**
     * TikTok queda fuera a propósito: su verify_connection no consulta la red (ver el caso de
     * abajo y la incidencia abierta en el repo).
     *
     * @return array<string, array{0: object, 1: array<string, string>}>
     */
    public static function canalesQueHablanConLaRed(): array
    {
        $todos = self::canales();
        unset($todos['tiktok']);

        return $todos;
    }

    /**
     * Hueco conocido: TikTok «verifica la conexión» validando solo el formato del token y del
     * open_id, sin preguntar a la API si el token vale. Devuelve éxito con un token inventado de
     * 40 caracteres. No se cambia el comportamiento desde una prueba: se deja afirmado para que
     * quede a la vista, y la incidencia está abierta en el repo.
     */
    public function test_tiktok_verificar_conexion_no_consulta_la_red(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_tiktok_token'] = str_repeat('t', 40);
        $GLOBALS['_cp_test_options']['convoca_publisher_tiktok_open_id'] = 'open-id-123456';

        $resultado = (new TikTok())->verify_connection();

        $this->assertTrue($resultado['success']);
        $this->assertSame([], $GLOBALS['_cp_test_http'], 'no ha habido ninguna petición: no se ha verificado nada');
    }

    /**
     * @dataProvider canalesQueHablanConLaRed
     */
    public function test_verificar_conexion_rechazada_no_dice_que_va(object $canal, array $opciones): void
    {
        foreach ($opciones as $clave => $valor) {
            $GLOBALS['_cp_test_options'][$clave] = $valor;
        }

        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 401], 'body' => '{"error":"token caducado","message":"token caducado"}'],
            ['response' => ['code' => 401], 'body' => '{"ok":false,"description":"token caducado"}'],
        ];

        $resultado = $canal->verify_connection();

        $this->assertFalse($resultado['success'], 'un 401 no puede pasar por conexión buena');
    }
}
