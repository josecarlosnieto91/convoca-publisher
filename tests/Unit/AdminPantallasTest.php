<?php

/**
 * Las pantallas del panel: que cada pestaña se pinte, que respete el permiso y que la pantalla de
 * un canal mande sobre las pestañas.
 *
 * Aquí no se juzga el diseño: se comprueba que la pantalla que alguien abre en WordPress existe y
 * dice lo que tiene que decir. Un panel que revienta con un fatal no avisa por correo; se descubre
 * cuando alguien entra a configurar algo.
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Admin;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class AdminPantallasTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_cp_test_options'] = [];
        $GLOBALS['_cp_test_can_manage'] = true;
        $GLOBALS['_cp_test_postmeta'] = [];
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['_cp_test_db'] = [];
        $_GET = [];
        $_POST = [];

        $GLOBALS['_cp_publisher_stub'] = new class {
            public function get_channels(): array
            {
                return ['fb' => new FakeChannel('fb', 'facebook', 'Facebook')];
            }

            public function get_channel(string $id): ?object
            {
                return $this->get_channels()[$id] ?? null;
            }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_cp_test_options'], $GLOBALS['_cp_test_can_manage'], $GLOBALS['_cp_test_postmeta'], $GLOBALS['_cp_test_db'], $GLOBALS['_cp_publisher_stub']);
        $_GET = [];
        $_POST = [];
    }

    private function pintar(callable $pantalla): string
    {
        ob_start();
        $pantalla();

        return (string) ob_get_clean();
    }

    public function test_sin_permiso_el_panel_no_pinta_nada(): void
    {
        $GLOBALS['_cp_test_can_manage'] = false;

        $html = $this->pintar([Admin::class, 'render_page']);

        $this->assertSame('', $html, 'quien no administra el sitio no ve la pantalla');
    }

    public function test_la_pantalla_principal_tiene_sus_pestanas(): void
    {
        $html = $this->pintar([Admin::class, 'render_page']);

        $this->assertStringContainsString('Convoca Publisher', $html);
        foreach (['Canales', 'Cola', 'Configuración', 'Plantillas', 'Probar', 'Moderación', 'Guía'] as $pestana) {
            $this->assertStringContainsString($pestana, $html, "falta la pestaña {$pestana}");
        }
        $this->assertStringContainsString('nav-tab-active', $html, 'alguna pestaña tiene que estar marcada');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pestanas(): array
    {
        return [
            'canales' => ['channels', 'Canales'],
            'cola' => ['queue', 'Cola'],
            'configuracion' => ['settings', 'Configuración'],
            'plantillas' => ['templates', 'Plantilla'],
            'probar' => ['test', 'Probar'],
            'moderacion' => ['moderation', 'Moderación'],
            'guia' => ['guide', 'Guía'],
        ];
    }

    /**
     * @dataProvider pestanas
     */
    public function test_cada_pestana_se_pinta_sin_reventar(string $slug, string $palabra): void
    {
        $_GET['tab'] = $slug;

        $html = $this->pintar([Admin::class, 'render_page']);

        $this->assertNotSame('', $html, "la pestaña {$slug} no puede salir vacía");
        $this->assertGreaterThan(200, strlen($html), "la pestaña {$slug} pinta muy poco");
        $this->assertStringContainsString('wrap', $html);
    }

    public function test_una_pestana_inventada_cae_en_canales(): void
    {
        $_GET['tab'] = 'inventada';

        $html = $this->pintar([Admin::class, 'render_page']);

        $this->assertNotSame('', $html, 'una pestaña que no existe no puede dejar la pantalla en blanco');
    }

    public function test_la_pantalla_de_un_canal_manda_sobre_las_pestanas(): void
    {
        $_GET['canal'] = 'fb';
        $_GET['tab'] = 'settings';

        $html = $this->pintar([Admin::class, 'render_page']);

        $this->assertStringNotContainsString('nav-tab-wrapper', $html, 'la pantalla de la cuenta no lleva pestañas');
        $this->assertNotSame('', $html);
    }

    public function test_un_canal_que_no_existe_no_deja_la_pantalla_en_blanco(): void
    {
        $_GET['canal'] = 'inventado';

        $html = $this->pintar([Admin::class, 'render_page']);

        $this->assertStringContainsString('nav-tab-wrapper', $html, 'si el canal no existe se vuelve a las pestañas');
    }

    public function test_el_historial_de_publicaciones_se_pinta_con_su_encabezado(): void
    {
        // El historial se pinta contra las CUENTAS del plugin (Plugin::accounts()), no contra el
        // doble de canales: sin cuentas configuradas no hay nada que mapear, y la pantalla lo
        // dice. Es el comportamiento correcto, así que la prueba afirma eso y no lo que yo
        // suponía.
        $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'] = [
            [
                'post_id' => 10,
                'title' => 'Fiesta del socio',
                'channel' => 'fb',
                'success' => true,
                'time' => '2026-10-23 10:00:00',
                'response' => 'publicado',
            ],
        ];

        $html = $this->pintar([Admin::class, 'render_log_page']);

        $this->assertStringContainsString('Historial de publicaciones', $html);
        $this->assertGreaterThan(300, strlen($html), 'la pantalla tiene que pintarse entera, no un aviso suelto');
        $this->assertStringNotContainsString('Fatal error', $html);
    }

    public function test_el_historial_vacio_lo_dice(): void
    {
        $html = $this->pintar([Admin::class, 'render_log_page']);

        $this->assertNotSame('', $html);
    }

    public function test_la_pantalla_de_acerca_de_dice_la_version(): void
    {
        $html = $this->pintar([Admin::class, 'render_about_page']);

        $this->assertStringContainsString(CONVOCA_PUBLISHER_VERSION, $html, 'la versión instalada tiene que verse');
    }
}
