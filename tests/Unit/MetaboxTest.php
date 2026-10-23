<?php

/**
 * La caja «Publicar en RRSS» del editor: lo que ve quien escribe la entrada y lo que se guarda.
 *
 * Estaba al 32,9 %. Lo que se prueba aquí es lo que puede hacer daño: que sin nonce o sin permiso
 * no se guarde nada, que una programación en el pasado no se acepte, que un mensaje vacío borre el
 * propio en vez de dejarlo pegado, y que el botón «Compartir ahora» conteste con error cuando no
 * puede compartir en vez de decir que sí.
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Metabox;
use ConvocaPublisher\Publisher;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class MetaboxTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_cp_test_options'] = [];
        $GLOBALS['_cp_test_postmeta'] = [];
        $GLOBALS['_cp_test_can_manage'] = true;
        $GLOBALS['_cp_test_metaboxes'] = [];
        $GLOBALS['_cp_test_scripts'] = [];
        $GLOBALS['_cp_test_titles'] = [10 => 'Fiesta del socio'];
        $GLOBALS['_cp_test_status'] = [10 => 'publish'];
        $GLOBALS['_cp_test_missing'] = [];
        $_POST = [];

        $ref = new \ReflectionProperty(Publisher::class, 'instance');
        $ref->setValue(null, null);

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
        unset(
            $GLOBALS['_cp_test_options'],
            $GLOBALS['_cp_test_postmeta'],
            $GLOBALS['_cp_test_can_manage'],
            $GLOBALS['_cp_test_metaboxes'],
            $GLOBALS['_cp_test_scripts'],
            $GLOBALS['_cp_test_titles'],
            $GLOBALS['_cp_test_status'],
            $GLOBALS['_cp_test_missing'],
            $GLOBALS['_cp_publisher_stub'],
            $GLOBALS['_cp_test_json'],
            $GLOBALS['_cp_test_current_post'],
            $GLOBALS['_cp_test_nonce_fields']
        );
        $_POST = [];

        $ref = new \ReflectionProperty(Publisher::class, 'instance');
        $ref->setValue(null, null);
    }

    private function post(): \WP_Post
    {
        $p = new \WP_Post((object) ['ID' => 10]);
        $p->post_title = 'Fiesta del socio';
        $p->post_author = 1;

        return $p;
    }

    private function render(): string
    {
        ob_start();
        Metabox::render($this->post());

        return (string) ob_get_clean();
    }

    // ── dónde sale la caja ──────────────────────────────────────────────────────────────────

    public function test_la_caja_sale_en_el_lateral_del_editor(): void
    {
        Metabox::register();

        $caja = $GLOBALS['_cp_test_metaboxes']['convoca-publisher'] ?? null;
        $this->assertNotNull($caja, 'la caja tiene que registrarse');
        $this->assertSame('side', $caja['context']);
        $this->assertSame('high', $caja['priority']);
        $this->assertSame('post', $caja['screen']);
    }

    // ── carga de scripts ────────────────────────────────────────────────────────────────────

    public function test_en_otra_pantalla_no_carga_nada(): void
    {
        Metabox::enqueue('edit.php');

        $this->assertSame([], $GLOBALS['_cp_test_scripts'], 'fuera del editor no se carga el JS');
    }

    public function test_sin_entrada_no_carga_nada(): void
    {
        $GLOBALS['_cp_test_missing'] = [0]; // get_post() sin argumentos no encuentra nada

        Metabox::enqueue('post.php');

        $this->assertSame([], $GLOBALS['_cp_test_scripts']);
    }

    public function test_en_el_editor_carga_su_js_y_los_datos_de_las_redes(): void
    {
        $GLOBALS['_cp_test_current_post'] = $this->post();

        Metabox::enqueue('post.php');

        $script = $GLOBALS['_cp_test_scripts']['convoca-publisher-metabox'] ?? null;
        $this->assertNotNull($script, 'el editor necesita el JS');
        $this->assertStringContainsString('metabox.js', $script['src']);
        $this->assertTrue($script['in_footer']);
    }

    // ── lo que se ve ───────────────────────────────────────────────────────────────────────

    public function test_sin_publicar_y_con_publicacion_automatica_lo_dice(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_auto_publish'] = true;

        $html = $this->render();

        $this->assertStringContainsString('Se publicará automáticamente', $html);
        $this->assertStringContainsString('name="convoca_publisher_message"', $html, 'falta la caja del mensaje');
        $this->assertStringContainsString('convoca_publisher_metabox_nonce', $html, 'sin nonce no se puede guardar');
    }

    public function test_sin_publicar_y_sin_automatica_explica_el_boton(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_auto_publish'] = false;

        $html = $this->render();

        $this->assertStringContainsString('No se publica solo', $html);
    }

    public function test_ya_publicado_enseña_el_resultado_de_cada_canal(): void
    {
        $GLOBALS['_cp_test_postmeta'][10] = [
            '_convoca_publisher_published' => true,
            '_convoca_publisher_publish_results' => [
                'fb' => ['success' => true, 'post_id' => '123_456'],
                'tg' => ['success' => false, 'error' => 'chat not found'],
                '_warnings' => ['Se recortó para que cupiera'],
            ],
        ];

        $html = $this->render();

        $this->assertStringContainsString('Publicado en redes', $html);
        $this->assertStringContainsString('123_456', $html, 'el id publicado se ve');
        $this->assertStringContainsString('chat not found', $html, 'el fallo se ve, no se tapa');
        $this->assertStringContainsString('Se recortó para que cupiera', $html, 'y los avisos también');
        $this->assertStringNotContainsString('_warnings', $html, 'la clave interna no se pinta');
    }

    public function test_las_casillas_de_canales_respetan_los_desactivados(): void
    {
        $GLOBALS['_cp_test_postmeta'][10] = [
            '_convoca_publisher_disabled_channels' => ['tg'],
        ];

        $html = $this->render();

        $this->assertStringContainsString('name="convoca_publisher_channels[fb]"', $html);
        $this->assertStringContainsString('name="convoca_publisher_channels[tg]"', $html);
        // El desactivado va sin `checked`; se comprueba sobre su propia casilla.
        $this->assertMatchesRegularExpression('/name="convoca_publisher_channels\[tg\]" value="1" >/', $html);
        $this->assertMatchesRegularExpression('/name="convoca_publisher_channels\[fb\]" value="1" checked>/', $html);
    }

    public function test_la_programacion_se_pinta_si_la_hay(): void
    {
        $GLOBALS['_cp_test_postmeta'][10] = [
            '_convoca_publisher_schedule_time' => strtotime('2026-12-01 18:00:00'),
        ];

        $html = $this->render();

        $this->assertStringContainsString('type="datetime-local"', $html);
        $this->assertStringContainsString('2026-12-01T18:00', $html, 'la hora guardada tiene que aparecer');
    }

    public function test_sin_cuentas_configuradas_lo_dice_en_vez_de_dejar_el_hueco(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('Todavía no hay ninguna cuenta configurada', $html);
    }

    // ── lo que se guarda ───────────────────────────────────────────────────────────────────

    /*
     * La guarda de autoguardado (DOING_AUTOSAVE) NO se prueba aquí, y no por olvido: es una
     * constante de PHP, definirla deja definida para el resto de la clase, y aislarla en su propio
     * proceso rompe PHPUnit cuando hay cobertura activa (CodeCoverage::codeCoverage() devuelve
     * null en el proceso hijo con pcov). Se comprueba en la batería de integración, con WordPress
     * de verdad, que es donde el autoguardado existe. Excepción escrita, no fichero pendiente.
     */

    public function test_sin_nonce_no_se_guarda_nada(): void
    {
        Metabox::save(10);

        $this->assertArrayNotHasKey(10, $GLOBALS['_cp_test_postmeta']);
    }

    public function test_sin_permiso_sobre_la_entrada_no_se_guarda_nada(): void
    {
        $_POST['convoca_publisher_metabox_nonce'] = 'nonce-valido';
        $GLOBALS['_cp_test_can_manage'] = false; // no puede editar esta entrada
        $_POST['convoca_publisher_message'] = 'no debería guardarse';

        Metabox::save(10);

        $this->assertArrayNotHasKey(10, $GLOBALS['_cp_test_postmeta'], 'sin permiso no se guarda nada');
    }

    public function test_guardar_el_mensaje_propio(): void
    {
        $this->guardarConNonce();
        $_POST['convoca_publisher_message'] = 'Mi mensaje propio';

        Metabox::save(10);

        $this->assertSame(
            'Mi mensaje propio',
            $GLOBALS['_cp_test_postmeta'][10][Publisher::MESSAGE_META] ?? null,
            'el mensaje propio se guarda en su meta'
        );
    }

    public function test_un_mensaje_vacio_borra_el_propio_y_vuelve_a_la_plantilla(): void
    {
        $GLOBALS['_cp_test_postmeta'][10][Publisher::MESSAGE_META] = 'viejo';
        $this->guardarConNonce();
        $_POST['convoca_publisher_message'] = '   ';

        Metabox::save(10);

        $this->assertArrayNotHasKey(Publisher::MESSAGE_META, $GLOBALS['_cp_test_postmeta'][10] ?? [], 'vacío = plantilla');
    }

    public function test_una_programacion_futura_se_guarda(): void
    {
        $this->guardarConNonce();
        $_POST['convoca_publisher_schedule_time'] = '2030-01-01T10:00';

        Metabox::save(10);

        $guardado = $GLOBALS['_cp_test_postmeta'][10]['_convoca_publisher_schedule_time'] ?? null;
        $this->assertSame(strtotime('2030-01-01T10:00'), $guardado);
    }

    public function test_una_programacion_en_el_pasado_no_se_acepta(): void
    {
        $this->guardarConNonce();
        $_POST['convoca_publisher_schedule_time'] = '2020-01-01T10:00';

        Metabox::save(10);

        $this->assertArrayNotHasKey('_convoca_publisher_schedule_time', $GLOBALS['_cp_test_postmeta'][10] ?? [], 'no se programa hacia atrás');
    }

    public function test_quitar_la_programacion_la_borra(): void
    {
        $GLOBALS['_cp_test_postmeta'][10]['_convoca_publisher_schedule_time'] = 1893456000;
        $this->guardarConNonce();
        $_POST['convoca_publisher_schedule_time'] = '';

        Metabox::save(10);

        $this->assertArrayNotHasKey('_convoca_publisher_schedule_time', $GLOBALS['_cp_test_postmeta'][10] ?? []);
    }

    public function test_las_casillas_guardan_que_canal_esta_desactivado(): void
    {
        $this->guardarConNonce();
        $_POST['convoca_publisher_channels'] = ['fb' => '1']; // tg no viene: desactivado

        Metabox::save(10);

        $this->assertSame(['tg'], $GLOBALS['_cp_test_postmeta'][10]['_convoca_publisher_disabled_channels'] ?? null);
    }

    /**
     * Prepara un guardado legítimo: nonce presente, válido y con permiso.
     */
    private function guardarConNonce(): void
    {
        $_POST['convoca_publisher_metabox_nonce'] = 'nonce-valido';
    }

    // ── el botón «Compartir ahora» ─────────────────────────────────────────────────────────

    public function test_compartir_sin_entrada_valida_contesta_error(): void
    {
        $_POST = ['post_id' => 0, 'cuenta' => 'fb'];

        try {
            Metabox::ajax_share();
            $this->fail('tenía que contestar con error');
        } catch (\RuntimeException $e) {
            $json = $GLOBALS['_cp_test_json'];
            $this->assertFalse($json['ok']);
            $this->assertSame(403, $json['status'], 'sin entrada no se comparte');
        }
    }

    public function test_compartir_sin_publicador_contesta_error(): void
    {
        $_POST = ['post_id' => 10, 'cuenta' => 'fb'];
        // Sin publicador inicializado, el singleton está a null.
        try {
            Metabox::ajax_share();
            $this->fail('tenía que contestar con error');
        } catch (\RuntimeException $e) {
            $this->assertFalse($GLOBALS['_cp_test_json']['ok']);
            $this->assertSame(500, $GLOBALS['_cp_test_json']['status']);
        }
    }
}
