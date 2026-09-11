<?php

/**
 * Panel de canales: estado por canal y render de cada pantalla.
 *
 * Lo que se fija aquí:
 *  · El estado que ve el usuario (✅ Configurado · ❌ Falta token · ⚠️ Error al verificar ·
 *    🔑 Necesita reconexión) y, sobre todo, que un resultado de verificación viejo no se
 *    enseñe cuando la credencial ya es otra.
 *  · Que cada pantalla pinta algo, no usa estilos sueltos y no manda a pestañas que no
 *    existen (el aviso «Configura al menos un token en la pestaña de Ajustes» mandaba a
 *    una pestaña inexistente: era síntoma de otro fallo, pero el texto también sobraba).
 *  · Que la pantalla de un canal trae todo junto: campos, plantilla, verificación y guía.
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Admin;
    use ConvocaPublisher\Profile_Store;
    use ConvocaPublisher\Plugin;
    use PHPUnit\Framework\TestCase;

    if (!defined('CONVOCA_PUBLISHER_PLUGIN_URL')) {
        define('CONVOCA_PUBLISHER_PLUGIN_URL', 'http://example.com/wp-content/plugins/convoca-publisher/');
    }

    require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/class-admin.php';

    final class AdminChannelsUiTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            $GLOBALS['_cp_test_options'] = [];
            $_GET                        = [];
            $_REQUEST                    = [];

            // Que el doble de otra prueba no se cuele en el camino de las pantallas.
            unset($GLOBALS['_cp_publisher_stub']);
        }

        private function canal(string $channel_id): object
        {
            $channels = Plugin::discover_channels();

            return $channels[$channel_id];
        }

        private function telegramConfigurado(): object
        {
            update_option('convoca_publisher_telegram_token', 'TOKEN-DE-PRUEBA');
            update_option('convoca_publisher_telegram_chat_id', '-1001234567890');

            return $this->canal('telegram');
        }

        private function render(array $get): string
        {
            $_GET = $get;

            ob_start();
            Admin::render_page();

            return (string) ob_get_clean();
        }

        // ── Estado del canal ────────────────────────────────────────────────

        public function testUnCanalSinCredencialesSeVeComoFaltaToken(): void
        {
            $status = Admin::channel_status($this->canal('telegram'));

            $this->assertSame('missing', $status['key']);
            $this->assertSame('❌', $status['icon']);
            $this->assertSame('Falta token', $status['label']);
        }

        public function testUnCanalConfiguradoSinVerificarSeVeConfigurado(): void
        {
            $status = Admin::channel_status($this->telegramConfigurado());

            $this->assertSame('ok', $status['key']);
            $this->assertSame('✅', $status['icon']);
        }

        public function testUnErrorDeCredencialSeVeComoNecesitaReconexion(): void
        {
            $channel = $this->telegramConfigurado();

            update_option('convoca_publisher_verify_status', [
                'telegram' => [
                    'success'     => false,
                    'message'     => 'Invalid token (401)',
                    'fingerprint' => Admin::channel_fingerprint($channel),
                ],
            ]);

            $status = Admin::channel_status($channel);

            $this->assertSame('reconnect', $status['key']);
            $this->assertSame('🔑', $status['icon']);
            $this->assertSame('Necesita reconexión', $status['label']);
            $this->assertSame('Invalid token (401)', $status['detail']);
        }

        public function testUnErrorQueNoEsDeCredencialesSeVeComoErrorAlVerificar(): void
        {
            $channel = $this->telegramConfigurado();

            update_option('convoca_publisher_verify_status', [
                'telegram' => [
                    'success'     => false,
                    'message'     => 'No se pudo conectar con el servidor',
                    'fingerprint' => Admin::channel_fingerprint($channel),
                ],
            ]);

            $status = Admin::channel_status($channel);

            $this->assertSame('error', $status['key']);
            $this->assertSame('⚠️', $status['icon']);
        }

        public function testUnErrorViejoSeIgnoraSiLaCredencialHaCambiado(): void
        {
            $channel = $this->telegramConfigurado();

            update_option('convoca_publisher_verify_status', [
                'telegram' => [
                    'success'     => false,
                    'message'     => 'Invalid token',
                    'fingerprint' => Admin::channel_fingerprint($channel),
                ],
            ]);

            $this->assertSame('reconnect', Admin::channel_status($channel)['key']);

            // Token nuevo: la verificación anterior ya no dice nada de esta configuración.
            update_option('convoca_publisher_telegram_token', 'OTRO-TOKEN');

            $this->assertSame('ok', Admin::channel_status($channel)['key']);
        }

        public function testDetectaLosErroresQueSuenanACredencial(): void
        {
            foreach (['Invalid token', 'HTTP 403', 'token expirado', 'Unauthorized', 'access token has expired', 'OAuth error'] as $message) {
                $this->assertTrue(Admin::looks_like_auth_error($message), $message);
            }

            foreach (['No se pudo conectar', 'Sin respuesta del servidor', 'Cuota agotada'] as $message) {
                $this->assertFalse(Admin::looks_like_auth_error($message), $message);
            }
        }

        // ── Pantallas ───────────────────────────────────────────────────────

        public function testLaPantallaDeCanalesListaLasSieteRedes(): void
        {
            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'channels']);

            foreach (['Telegram', 'Mastodon', 'Facebook', 'LinkedIn', 'Twitter', 'TikTok', 'Google'] as $nombre) {
                $this->assertStringContainsString($nombre, $html);
            }

            $this->assertStringContainsString('cp-status', $html);
            $this->assertStringContainsString('Falta token', $html);
            $this->assertStringContainsString('Añadir cuenta', $html);
        }

        public function testUnaRedConCuentasLasListaConEstadoYAcciones(): void
        {
            Profile_Store::create('telegram', 'Telegram — Centro Social', [
                'convoca_publisher_telegram_token'   => 'TOKEN',
                'convoca_publisher_telegram_chat_id' => '-100',
            ]);

            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'channels']);

            $this->assertStringContainsString('Telegram — Centro Social', $html);
            $this->assertStringContainsString('1 cuenta', $html);
            $this->assertStringContainsString('Configurar', $html);
            $this->assertStringContainsString('Verificar conexión', $html);
        }

        public function testSinCanalesConfiguradosSaleElAsistenteDeInicio(): void
        {
            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'channels']);

            $this->assertStringContainsString('Por dónde empezar', $html);
            $this->assertStringContainsString('Empezar por Telegram', $html);
        }

        public function testConUnCanalConfiguradoYaNoSaleElAsistente(): void
        {
            $this->telegramConfigurado();

            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'channels']);

            $this->assertStringNotContainsString('Por dónde empezar', $html);
        }

        public function testLaPantallaDeUnaCuentaTraeTodoJunto(): void
        {
            $cuenta = Profile_Store::create('telegram', 'Telegram — Centro Social', [
                'convoca_publisher_telegram_token'   => 'TOKEN',
                'convoca_publisher_telegram_chat_id' => '-100',
            ], '{title} propio');

            $this->assertIsArray($cuenta);

            $html = $this->render(['page' => 'convoca-publisher', 'canal' => $cuenta['id']]);

            $this->assertStringContainsString('name="convoca_publisher_telegram_token"', $html);
            $this->assertStringContainsString('name="convoca_publisher_telegram_chat_id"', $html);
            $this->assertStringContainsString('cuenta_plantilla', $html);
            $this->assertStringContainsString('{title} propio', $html);
            $this->assertStringContainsString('Verificar conexión', $html);
            $this->assertStringContainsString('#canal-telegram', $html);
            $this->assertStringContainsString('Todos los canales', $html);
            $this->assertStringContainsString('Borrar cuenta', $html);
        }

        public function testLaPantallaDeCuentaNuevaPideLosDatosYNoTraeBorrar(): void
        {
            $html = $this->render(['page' => 'convoca-publisher', 'canal' => 'telegram', 'nueva' => 1]);

            $this->assertStringContainsString('Crear cuenta', $html);
            $this->assertStringContainsString('Mientras falten datos, esta cuenta no publicará nada', $html);
            $this->assertStringContainsString('name="convoca_publisher_telegram_token"', $html);
            $this->assertStringNotContainsString('Borrar cuenta', $html);
        }

        public function testSeAvisaCuandoLaRedLlegaAlLimiteDeCuentas(): void
        {
            for ($i = 1; $i <= Profile_Store::LIMIT_PER_NETWORK; ++$i) {
                Profile_Store::create('telegram', 'Cuenta ' . $i);
            }

            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'channels']);

            $this->assertStringContainsString('Límite alcanzado', $html);
        }

        public function testNingunaPantallaUsaEstilosSueltosNiMencionaAjustes(): void
        {
            $screens = [
                ['page' => 'convoca-publisher', 'tab' => 'channels'],
                ['page' => 'convoca-publisher', 'tab' => 'settings'],
                ['page' => 'convoca-publisher', 'tab' => 'templates'],
                ['page' => 'convoca-publisher', 'tab' => 'test'],
                ['page' => 'convoca-publisher', 'tab' => 'moderation'],
                ['page' => 'convoca-publisher', 'tab' => 'guide'],
                ['page' => 'convoca-publisher', 'canal' => 'telegram', 'nueva' => 1],
                ['page' => 'convoca-publisher', 'canal' => 'mastodon'],
                ['page' => 'convoca-publisher', 'canal' => 'linkedin'],
            ];

            foreach ($screens as $get) {
                $html    = $this->render($get);
                $etiqueta = (string) wp_json_encode($get);

                $this->assertNotSame('', $html, 'La pantalla ' . $etiqueta . ' no pinta nada.');
                $this->assertStringNotContainsString('style="', $html, 'Estilo suelto en ' . $etiqueta);
                $this->assertStringNotContainsString('Ajustes', $html, 'Menciona una pestaña que no existe: ' . $etiqueta);
            }
        }

        public function testCadaCanalTieneSuAnclaEnLaGuia(): void
        {
            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'guide']);

            foreach (['facebook', 'linkedin', 'twitter', 'tiktok', 'googlemybusiness', 'telegram', 'mastodon'] as $channel_id) {
                $this->assertStringContainsString('id="canal-' . $channel_id . '"', $html);
            }
        }

        public function testLaUrlDeUnaPestanaApuntaAlPanel(): void
        {
            $url = Admin::tab_url('channels', ['canal' => 'telegram']);

            $this->assertStringContainsString('page=convoca-publisher', $url);
            $this->assertStringContainsString('tab=channels', $url);
            $this->assertStringContainsString('canal=telegram', $url);
        }
    }
}

namespace {

    if (!function_exists('convoca_publisher')) {
        /**
         * Doble del acceso al plugin para poder renderizar el panel sin arrancar WordPress.
         *
         * Este fichero se carga antes de que corran las pruebas, así que es el que define la
         * función global; si no respetara el doble de otra prueba, le taparía el suyo. Por eso
         * mira primero `_cp_publisher_stub` (la convención de `RetryAndModerationTest`).
         */
        function convoca_publisher(): object
        {
            if (isset($GLOBALS['_cp_publisher_stub'])) {
                return $GLOBALS['_cp_publisher_stub'];
            }

            return new class {
                public function get_channels(): array
                {
                    return \ConvocaPublisher\Plugin::accounts();
                }

                public function get_channel(string $channel_id): ?object
                {
                    $accounts = \ConvocaPublisher\Plugin::accounts();

                    return $accounts[$channel_id] ?? \ConvocaPublisher\Plugin::networks()[$channel_id] ?? null;
                }
            };
        }
    }
}
