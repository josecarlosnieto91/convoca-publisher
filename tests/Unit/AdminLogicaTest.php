<?php

/**
 * La lógica del panel: saneadores de ajustes y estado de un canal.
 *
 * `class-admin.php` es el fichero más grande del plugin (1.671 statements) y estaba al 59,5 %.
 * Aquí se ataca lo que no es pintar HTML: los saneadores (que reciben lo que llegue del
 * formulario) y el cálculo del estado de un canal, que es lo que decide si se le pide a alguien
 * que vuelva a conectar su cuenta.
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Admin;
use ConvocaPublisher\Queue;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class AdminLogicaTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_cp_test_options'] = [];
        $_POST = [];

        $GLOBALS['_cp_publisher_stub'] = new class {
            public function get_channels(): array
            {
                return [
                    'fb' => new FakeChannel('fb', 'facebook', 'Facebook'),
                    'tg' => new FakeChannel('tg', 'telegram', 'Telegram'),
                ];
            }

            public function get_channel(string $id): ?object
            {
                return $this->get_channels()[$id] ?? null;
            }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_cp_test_options'], $GLOBALS['_cp_publisher_stub']);
        $_POST = [];
    }

    // ── saneadores ─────────────────────────────────────────────────────────────────────────

    public function test_la_casilla_se_guarda_como_uno_o_vacio(): void
    {
        $this->assertSame('1', Admin::sanitize_checkbox('1'));
        $this->assertSame('1', Admin::sanitize_checkbox('on'));
        $this->assertSame('', Admin::sanitize_checkbox(''));
        $this->assertSame('', Admin::sanitize_checkbox(null));
        $this->assertSame('', Admin::sanitize_checkbox('0'));
    }

    public function test_el_intervalo_se_recorta_a_lo_que_admite_la_cola(): void
    {
        $this->assertSame(0, Admin::sanitize_queue_interval(0), '0 es «sin espaciado»');
        $this->assertSame(Queue::clamp_interval(1800), Admin::sanitize_queue_interval(1800));
        $this->assertGreaterThanOrEqual(0, Admin::sanitize_queue_interval(-500), 'un negativo no puede colarse');
        // Y no escribe la opción: un saneador que guarda se llama a sí mismo sin fin.
        $this->assertArrayNotHasKey(Queue::INTERVAL_OPTION, $GLOBALS['_cp_test_options'], 'el saneador no guarda');
    }

    public function test_el_modo_de_moderacion_solo_admite_los_tres_validos(): void
    {
        $this->assertSame('off', Admin::sanitize_moderation_mode('off'));
        $this->assertSame('all', Admin::sanitize_moderation_mode('all'));
        $this->assertSame('canal', Admin::sanitize_moderation_mode('canal'));
        $this->assertSame('off', Admin::sanitize_moderation_mode('loquesea'), 'cualquier otra cosa cae a off');
        $this->assertSame('off', Admin::sanitize_moderation_mode(''));
    }

    public function test_el_aviso_de_privacidad_no_se_desmarca_solo_por_guardar_otra_pestana(): void
    {
        // Guardando desde Plantillas, el campo no viaja en el POST: se conserva lo guardado.
        $GLOBALS['_cp_test_options']['convoca_publisher_privacy_acknowledged'] = true;
        $this->assertTrue(Admin::sanitize_privacy_ack(false), 'sin el campo en el POST se conserva el valor');

        // Y si viaja, manda el POST.
        $_POST['convoca_publisher_privacy_acknowledged'] = '1';
        $this->assertTrue(Admin::sanitize_privacy_ack('1'));
        $this->assertFalse(Admin::sanitize_privacy_ack(''));
    }

    public function test_los_canales_con_moderacion_se_filtran_a_los_que_existen(): void
    {
        $limpio = Admin::sanitize_moderation_channels(['fb', 'inventado', 'tg']);

        $this->assertSame(['fb', 'tg'], $limpio, 'un canal que ya no existe no se guarda');
    }

    public function test_una_lista_que_no_es_lista_deja_la_moderacion_vacia(): void
    {
        $this->assertSame([], Admin::sanitize_moderation_channels('fb'));
        $this->assertSame([], Admin::sanitize_moderation_channels(null));
    }

    // ── direcciones del panel ──────────────────────────────────────────────────────────────

    public function test_la_direccion_de_una_pestana_lleva_pagina_y_pestana(): void
    {
        $url = Admin::tab_url('canales');

        $this->assertStringContainsString('page=convoca-publisher', $url);
        $this->assertStringContainsString('tab=canales', $url);
    }

    public function test_la_direccion_admite_parametros_extra(): void
    {
        $url = Admin::tab_url('canales', ['channel' => 'fb']);

        $this->assertStringContainsString('channel=fb', $url);
        $this->assertStringContainsString('tab=canales', $url);
    }

    // ── el estado del canal ────────────────────────────────────────────────────────────────

    public function test_un_canal_sin_credenciales_se_ve_como_no_configurado(): void
    {
        // El canal de verdad, sin credenciales: es el caso que ve quien entra al panel por
        // primera vez. (FakeChannel es final y además presume siempre de estar disponible.)
        $canal = new \ConvocaPublisher\Channels\Facebook();

        $estado = Admin::channel_status($canal);

        $this->assertSame('missing', $estado['key'], 'sin token el estado es «falta token»');
        $this->assertNotEmpty($estado['label']);
    }

    public function test_un_fallo_de_token_pide_reconectar_en_vez_de_error_generico(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_verify_status'] = [
            'fb' => ['success' => false, 'message' => 'The access token is invalid', 'fingerprint' => 'huella'],
        ];
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');

        // La huella se calcula con los ajustes del canal: se fija la misma que la guardada.
        $GLOBALS['_cp_test_options']['convoca_publisher_verify_status']['fb']['fingerprint'] = Admin::channel_fingerprint($canal);

        $estado = Admin::channel_status($canal);

        $this->assertSame('reconnect', $estado['key'], 'un token inválido se arregla reconectando');
        $this->assertStringContainsString('token', strtolower((string) $estado['detail']));
    }

    public function test_un_fallo_que_no_es_de_credenciales_se_ve_como_error(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $GLOBALS['_cp_test_options']['convoca_publisher_verify_status'] = [
            'fb' => [
                'success' => false,
                'message' => 'la red no responde',
                'fingerprint' => Admin::channel_fingerprint($canal),
            ],
        ];

        $estado = Admin::channel_status($canal);

        $this->assertSame('error', $estado['key']);
    }

    public function test_si_la_configuracion_cambio_el_estado_guardado_ya_no_vale(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $GLOBALS['_cp_test_options']['convoca_publisher_verify_status'] = [
            'fb' => ['success' => false, 'message' => 'token caducado', 'fingerprint' => 'huella-vieja'],
        ];

        $estado = Admin::channel_status($canal);

        $this->assertContains($estado['key'], ['unverified', 'ok'], 'un resultado de una configuración anterior no puede mandar');
    }

    public function test_un_canal_verificado_hace_poco_se_ve_bien(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $GLOBALS['_cp_test_options']['convoca_publisher_verify_status'] = [
            'fb' => [
                'success' => true,
                'time' => gmdate('Y-m-d H:i:s'),
                'fingerprint' => Admin::channel_fingerprint($canal),
            ],
        ];

        $estado = Admin::channel_status($canal);

        $this->assertContains($estado['key'], ['ok', 'expiring', 'unverified'], 'un canal recién verificado no está roto');
    }

    // ── candidatas para probar una publicación ────────────────────────────────────────────

    public function test_las_candidatas_llevan_id_y_una_etiqueta_legible(): void
    {
        $GLOBALS['_cp_test_posts'] = [10, 11];
        $GLOBALS['_cp_test_titles'] = [10 => 'Fiesta del socio', 11 => 'Otra fiesta'];

        $candidatas = Admin::test_candidates(5);

        $this->assertCount(2, $candidatas);
        $this->assertSame(10, $candidatas[0]['id']);
        $this->assertStringContainsString('Fiesta del socio', $candidatas[0]['label'], 'la etiqueta tiene que poder leerse');
    }

    public function test_sin_entradas_no_hay_candidatas(): void
    {
        $GLOBALS['_cp_test_posts'] = [];

        $this->assertSame([], Admin::test_candidates());
    }

    // ── reconocer un error de credenciales ────────────────────────────────────────────────

    public function test_reconoce_los_errores_de_credenciales(): void
    {
        foreach (['The access token is invalid', '401 Unauthorized', 'token caducado', 'Forbidden', 'oauth error'] as $mensaje) {
            $this->assertTrue(Admin::looks_like_auth_error($mensaje), "«{$mensaje}» es de credenciales");
        }
    }

    public function test_no_confunde_un_error_de_red_con_credenciales(): void
    {
        foreach (['la red no responde', 'timeout tras 30s', 'chat not found'] as $mensaje) {
            $this->assertFalse(Admin::looks_like_auth_error($mensaje), "«{$mensaje}» no es de credenciales");
        }
    }

    // ── pintar el estado ──────────────────────────────────────────────────────────────────

    public function test_la_etiqueta_de_estado_lleva_su_clase_y_su_texto(): void
    {
        $html = Admin::status_badge(['class' => 'cp-status--ok', 'icon' => '✅', 'label' => 'Conectado']);

        $this->assertStringContainsString('cp-status--ok', $html);
        $this->assertStringContainsString('Conectado', $html);
        $this->assertStringContainsString('✅', $html);
    }

    public function test_el_boton_de_verificar_apunta_a_la_accion_con_el_canal(): void
    {
        $html = Admin::verify_button(new FakeChannel('fb', 'facebook', 'Facebook'));

        $this->assertStringContainsString('action=convoca_publisher_verify_channel', $html);
        $this->assertStringContainsString('channel=fb', $html);
        $this->assertStringContainsString('Verificar conexión', $html);
    }
}
