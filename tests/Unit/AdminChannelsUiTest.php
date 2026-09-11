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
    use ConvocaPublisher\Publisher;
    use PHPUnit\Framework\TestCase;

    if (!defined('CONVOCA_PUBLISHER_PLUGIN_URL')) {
        define('CONVOCA_PUBLISHER_PLUGIN_URL', 'http://example.com/wp-content/plugins/convoca-publisher/');
    }

    require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/class-admin.php';

    final class AdminChannelsUiTest extends TestCase
    {
        protected function setUp(): void
        {
            \cp_test_reset();

            $GLOBALS['_cp_test_postmeta'] = [];
            parent::setUp();

            $GLOBALS['_cp_test_options'] = [];
            $_GET                        = [];
            $_REQUEST                    = [];

            // Que el doble de otra prueba no se cuele en el camino de las pantallas.
            unset($GLOBALS['_cp_publisher_stub']);
        }

        public function testLaPruebaDePublicacionListaLasEntradas(): void
        {
            // El desplegable salía VACÍO en producción: usaba `wp_dropdown_pages()`, que trabaja
            // con páginas (con `post_type => 'post'` no pinta nada). Sin entradas no hay forma
            // de probar la integración, que es justo lo que ofrece esta pestaña.
            $GLOBALS['_cp_test_posts']  = [1125, 306];
            $GLOBALS['_cp_test_titles'] = [1125 => 'Taller de huerto', 306 => 'Vegan Cooking Workshop'];

            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'test']);

            $this->assertStringContainsString('name="convoca_publisher_test_post_id"', $html, 'Hay desplegable de entradas.');
            $this->assertStringContainsString('value="1125"', $html, 'Aparece la primera entrada.');
            $this->assertStringContainsString('Taller de huerto', $html, 'Con su título.');
            $this->assertStringNotContainsString('No hay entradas publicadas', $html, 'Y no sale el aviso de que no hay nada que probar.');
        }

        public function testSiNoHayEntradasPublicadasSeDiceEnVezDeDejarElDesplegableMudo(): void
        {
            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'test']);

            $this->assertStringContainsString('No hay entradas publicadas', $html, 'Se explica, en vez de dejar un desplegable vacío sin decir por qué.');
        }

        public function testLasPlantillasSonMultilineaYEnsenanLaDeFabrica(): void
        {
            // Eran campos de una línea, y no por gusto: el saneado era `sanitize_text_field`,
            // que borra los saltos. Una plantilla de tres líneas volvía hecha una.
            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'templates']);

            $this->assertSame(8, substr_count($html, '<textarea'), 'Una global y una por red (7), todas multilínea.');
            $this->assertStringNotContainsString(
                '<input type="text" name="convoca_publisher_message_template"',
                $html,
                'El campo global ya no es de una línea.'
            );
            $this->assertStringContainsString('la de fábrica para esta red', $html, 'Cada red dice con qué se queda si no escribes nada.');
        }

        public function testLasVariablesSalenDeUnaSolaListaYSePuedenInsertar(): void
        {
            // La ayuda y los botones salen de Publisher::variables(): si fueran dos listas, la
            // ayuda acabaría prometiendo una variable que ya no existe.
            $html = $this->render(['page' => 'convoca-publisher', 'tab' => 'templates']);

            $this->assertCount(11, Publisher::variables(), 'Las siete de siempre más las cuatro del encargo.');
            foreach (array_keys(Publisher::variables()) as $variable) {
                $this->assertStringContainsString(
                    'data-cp-insert="' . $variable . '"',
                    $html,
                    'Hay botón para insertar ' . $variable . '.'
                );
            }
            $this->assertStringContainsString('data-cp-preview', $html, 'Cada red tiene su panel de vista previa.');
            $this->assertStringContainsString('data-cp-reset', $html, 'Y su botón de volver a la de fábrica.');
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
}
