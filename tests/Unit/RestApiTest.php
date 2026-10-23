<?php

/**
 * La API REST del plugin: rutas, permiso, estado y publicación.
 *
 * Este fichero estaba al 0 % de cobertura (192 statements) no por falta de ganas, sino porque
 * sus firmas tipan contra `WP_REST_Request`/`WP_REST_Response` y el arnés no tenía esos dobles:
 * la clase no se podía ni cargar. Lo que se prueba aquí es el comportamiento que ve el cliente
 * de la API —códigos de estado, qué se publica y qué no, y que sin permiso no se pasa— y no que
 * las funciones existan.
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Rest;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class RestApiTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_cp_rest_routes'] = [];
        unset($GLOBALS['_cp_publisher_stub']);

        // Entradas: la 10 publicada, la 11 en borrador, la 404 inexistente.
        $GLOBALS['_cp_test_titles'] = [10 => 'Fiesta del socio', 11 => 'Borrador'];
        $GLOBALS['_cp_test_status'] = [10 => 'publish', 11 => 'draft'];
        $GLOBALS['_cp_test_missing'] = [404];
        $GLOBALS['_cp_test_tags'] = [10 => ['Fiesta Mayor', 'Socio_2026']];

        // El publicador es un singleton: se limpia para que cada prueba monte el suyo.
        // Sin setAccessible(): desde PHP 8.1 la reflexión llega igual y la llamada está
        // deprecada en 8.5 (el CI corre 8.4 y no la ve, el equipo local sí).
        $ref = new \ReflectionProperty(\ConvocaPublisher\Publisher::class, 'instance');
        $ref->setValue(null, null);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_cp_publisher_stub'],
            $GLOBALS['_cp_rest_routes'],
            $GLOBALS['_cp_caps'],
            $GLOBALS['_cp_test_can_manage'],
            $GLOBALS['_cp_test_titles'],
            $GLOBALS['_cp_test_status'],
            $GLOBALS['_cp_test_missing'],
            $GLOBALS['_cp_test_tags'],
            $GLOBALS['_cp_test_options']
        );
    }

    /**
     * Un plugin de mentira con los canales que pida cada caso.
     *
     * @param array<string, FakeChannel> $canales
     */
    private function inyectarCanales(array $canales, array $resultadoPublicacion = []): void
    {
        $publicaciones = [];

        $GLOBALS['_cp_publisher_stub'] = new class ($canales, $resultadoPublicacion, $publicaciones) {
            public function __construct(
                private array $canales,
                private array $resultado,
                public array &$publicaciones,
            ) {
            }

            public function get_channels(): array
            {
                return $this->canales;
            }

            public function publish_post(int $post_id, bool $force = false, bool $approved = false): array
            {
                $this->publicaciones[] = ['post_id' => $post_id, 'force' => $force, 'approved' => $approved];

                return $this->resultado;
            }
        };

        // El camino con filtro comprueba Publisher::instance() antes de publicar: se monta también.
        $GLOBALS['_cp_test_titles'] = $GLOBALS['_cp_test_titles'] ?? [];
        \ConvocaPublisher\Publisher::init($canales);
    }

    public function test_registra_las_tres_rutas_con_su_metodo_y_su_permiso(): void
    {
        Rest::register_routes();

        $rutas = $GLOBALS['_cp_rest_routes'];

        $this->assertSame(
            ['convoca-publisher/v1/test/(?P<channel>[a-z_]+)', 'convoca-publisher/v1/status', 'convoca-publisher/v1/publish/(?P<post_id>\d+)'],
            array_keys($rutas)
        );

        $this->assertSame('POST', $rutas['convoca-publisher/v1/test/(?P<channel>[a-z_]+)']['methods']);
        $this->assertSame('GET', $rutas['convoca-publisher/v1/status']['methods']);
        $this->assertSame('POST', $rutas['convoca-publisher/v1/publish/(?P<post_id>\d+)']['methods']);

        foreach ($rutas as $ruta) {
            $this->assertSame([Rest::class, 'check_permission'], $ruta['permission_callback']);
        }
    }

    public function test_el_permiso_depende_de_la_capacidad(): void
    {
        $GLOBALS['_cp_test_can_manage'] = false;
        $this->assertFalse(Rest::check_permission(new \WP_REST_Request()), 'sin manage_options no se pasa');

        $GLOBALS['_cp_test_can_manage'] = true;
        $this->assertTrue(Rest::check_permission(new \WP_REST_Request()), 'con manage_options se pasa');
    }

    public function test_el_estado_cuenta_los_canales_y_su_version(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->inyectarCanales(['fb' => $canal]);

        $respuesta = Rest::get_status();
        $datos = $respuesta->get_data();

        $this->assertSame(200, $respuesta->get_status());
        $this->assertSame(['fb'], array_keys($datos['channels']));
        $this->assertSame('Facebook', $datos['channels']['fb']['name']);
        $this->assertTrue($datos['channels']['fb']['available']);
        $this->assertSame(CONVOCA_PUBLISHER_VERSION, $datos['version']);
    }

    public function test_probar_un_canal_que_no_existe_da_404(): void
    {
        $this->inyectarCanales(['fb' => new FakeChannel('fb', 'facebook', 'Facebook')]);

        $respuesta = Rest::test_channel(new \WP_REST_Request(['channel' => 'mastodon', 'post_id' => 10]));

        $this->assertSame(404, $respuesta->get_status());
        $this->assertFalse($respuesta->get_data()['success']);
    }

    public function test_probar_un_canal_sobre_un_post_que_no_existe_da_404(): void
    {
        $this->inyectarCanales(['fb' => new FakeChannel('fb', 'facebook', 'Facebook')]);

        $respuesta = Rest::test_channel(new \WP_REST_Request(['channel' => 'fb', 'post_id' => 404]));

        $this->assertSame(404, $respuesta->get_status());
        $this->assertFalse($respuesta->get_data()['success']);
    }

    public function test_probar_un_canal_publica_en_simulacion_y_devuelve_su_resultado(): void
    {
        $this->inyectarCanales(['fb' => new FakeChannel('fb', 'facebook', 'Facebook')]);

        $respuesta = Rest::test_channel(new \WP_REST_Request(['channel' => 'fb', 'post_id' => 10]));
        $datos     = $respuesta->get_data();

        $this->assertSame(200, $respuesta->get_status(), 'un canal que responde bien devuelve 200');
        $this->assertArrayHasKey('success', $datos);
        $this->assertTrue((bool) $datos['success']);
    }

    public function test_probar_un_canal_que_falla_devuelve_500(): void
    {
        $this->inyectarCanales(['fb' => new FakeChannel('fb', 'facebook', 'Facebook', false, 'token caducado')]);

        $respuesta = Rest::test_channel(new \WP_REST_Request(['channel' => 'fb', 'post_id' => 10]));
        $datos     = $respuesta->get_data();

        $this->assertSame(500, $respuesta->get_status(), 'si el canal no publica, la prueba responde 500');
        $this->assertFalse((bool) ($datos['success'] ?? false));
    }

    public function test_publicar_un_borrador_se_rechaza(): void
    {
        $this->inyectarCanales(['fb' => new FakeChannel('fb', 'facebook', 'Facebook')]);

        $respuesta = Rest::publish_post(new \WP_REST_Request(['post_id' => 11]));

        $this->assertSame(404, $respuesta->get_status());
        $this->assertFalse($respuesta->get_data()['success']);
    }

    public function test_publicar_un_post_que_no_existe_se_rechaza(): void
    {
        $this->inyectarCanales(['fb' => new FakeChannel('fb', 'facebook', 'Facebook')]);

        $respuesta = Rest::publish_post(new \WP_REST_Request(['post_id' => 404]));

        $this->assertSame(404, $respuesta->get_status());
    }

    public function test_dry_run_simula_todos_los_canales_sin_publicar(): void
    {
        $this->inyectarCanales([
            'fb' => new FakeChannel('fb', 'facebook', 'Facebook'),
            'tg' => new FakeChannel('tg', 'telegram', 'Telegram'),
        ]);

        $respuesta = Rest::publish_post(new \WP_REST_Request(['post_id' => 10, 'dry_run' => true]));
        $datos = $respuesta->get_data();

        $this->assertSame(200, $respuesta->get_status());
        $this->assertTrue($datos['dry_run']);
        $this->assertSame(['fb', 'tg'], array_keys($datos['results']));
        $this->assertSame('Facebook', $datos['results']['fb']['channel']);
        $this->assertStringContainsString('Simulación', $datos['results']['fb']['message']);
    }

    public function test_dry_run_con_filtro_solo_simula_los_pedidos(): void
    {
        $this->inyectarCanales([
            'fb' => new FakeChannel('fb', 'facebook', 'Facebook'),
            'tg' => new FakeChannel('tg', 'telegram', 'Telegram'),
        ]);

        $respuesta = Rest::publish_post(new \WP_REST_Request(['post_id' => 10, 'dry_run' => true, 'channels' => ['tg']]));

        $this->assertSame(['tg'], array_keys($respuesta->get_data()['results']));
    }

    public function test_publicar_con_filtro_solo_toca_los_canales_disponibles(): void
    {
        $disponible = new FakeChannel('fb', 'facebook', 'Facebook');

        // FakeChannel::is_available() devuelve siempre true a propósito (la disponibilidad real se
        // prueba con cuentas de verdad) y además es final, así que el canal «no disponible» se
        // declara aquí implementando la interfaz.
        $caido = new class implements \ConvocaPublisher\Channels\ChannelInterface {
            public array $sent = [];

            public function get_id(): string
            {
                return 'tg';
            }

            public function get_name(): string
            {
                return 'Telegram';
            }

            public function is_available(): bool
            {
                return false;
            }

            public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
            {
                $this->sent[] = ['message' => $message, 'url' => $url];

                return ['success' => true];
            }

            public function get_settings_fields(): array
            {
                return [];
            }

            public function validate_settings(array $settings): array
            {
                return $settings;
            }

            public function verify_connection(): array
            {
                return ['success' => false];
            }
        };

        $this->inyectarCanales(['fb' => $disponible, 'tg' => $caido]);

        $respuesta = Rest::publish_post(new \WP_REST_Request(['post_id' => 10, 'channels' => ['fb', 'tg']]));
        $resultados = $respuesta->get_data()['results'];

        $this->assertSame(200, $respuesta->get_status());
        $this->assertSame(['fb'], array_keys($resultados), 'un canal no disponible no debe intentar publicar');
        $this->assertCount(1, $disponible->sent, 'el canal disponible tiene que recibir el mensaje');
        $this->assertCount(0, $caido->sent);
    }

    public function test_el_mensaje_sustituye_la_plantilla_con_titulo_url_y_hashtags(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->inyectarCanales(['fb' => $canal]);

        // La plantilla por defecto es «{title} — {url}» y NO lleva hashtags: para probar la
        // sustitución de {hashtags} hay que poner una plantilla que los pida, como haría el sitio.
        $GLOBALS['_cp_test_options']['convoca_publisher_message_template'] = '{title} | {url} | {hashtags}';

        Rest::publish_post(new \WP_REST_Request(['post_id' => 10, 'channels' => ['fb']]));

        $mensaje = $canal->sent[0]['message'];
        $this->assertStringContainsString('Fiesta del socio', $mensaje);
        $this->assertStringContainsString('https://example.com/?p=10', $mensaje);
        $this->assertStringContainsString('#fiestamayor', $mensaje, 'las etiquetas se normalizan a hashtag');
        $this->assertStringContainsString('#socio2026', $mensaje, 'los guiones bajos se quitan');
        $this->assertStringNotContainsString('{title}', $mensaje, 'no puede quedar ningún marcador sin sustituir');
    }

    public function test_la_plantilla_por_defecto_no_lleva_hashtags(): void
    {
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->inyectarCanales(['fb' => $canal]);

        Rest::publish_post(new \WP_REST_Request(['post_id' => 10, 'channels' => ['fb']]));

        $mensaje = $canal->sent[0]['message'];
        $this->assertStringContainsString('Fiesta del socio', $mensaje);
        $this->assertStringNotContainsString('#', $mensaje, 'la plantilla por defecto es título y url');
    }

    public function test_sin_filtro_delega_en_el_publicador(): void
    {
        // El camino sin filtro no usa convoca_publisher(), usa Publisher::instance().
        $canal = new FakeChannel('fb', 'facebook', 'Facebook');
        $this->inyectarCanales(['fb' => $canal]);
        \ConvocaPublisher\Publisher::init(['fb' => $canal]);

        $respuesta = Rest::publish_post(new \WP_REST_Request(['post_id' => 10]));
        $datos = $respuesta->get_data();

        $this->assertSame(200, $respuesta->get_status());
        $this->assertTrue($datos['success']);
        $this->assertArrayHasKey('fb', $datos['results'], 'el canal tiene que aparecer en el resultado');
    }
}
