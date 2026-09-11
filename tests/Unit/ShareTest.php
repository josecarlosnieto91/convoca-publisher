<?php

/**
 * Compartir a mano: mensaje propio de la entrada, una cuenta concreta y lo que admite cada red.
 *
 * Lo que se fija aquí:
 * - De dónde sale el mensaje (lo que se escribe para este envío manda sobre todo lo demás).
 * - Que compartir en una cuenta **no** publique en las otras.
 * - Que lo que no cabe se recorte de verdad y quede dicho, en vez de fallar en silencio.
 * - Que la caja del editor enseñe el mensaje, su recuento y un botón por cuenta.
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Admin;
    use ConvocaPublisher\Metabox;
    use ConvocaPublisher\Plugin;
    use ConvocaPublisher\Profile_Store;
    use ConvocaPublisher\Publisher;
    use ConvocaPublisher\Tests\Support\FakeChannel;
    use PHPUnit\Framework\TestCase;

    final class ShareTest extends TestCase
    {
        /** @var array<string, object> */
        private array $canales = [];

        protected function setUp(): void
        {
            \cp_test_reset();

            parent::setUp();

            $GLOBALS['_cp_test_options']   = [];
            $GLOBALS['_cp_test_postmeta']  = [];
            $GLOBALS['_cp_test_posts']     = [];
            $GLOBALS['_cp_test_titles']    = [];
            $GLOBALS['_cp_test_db']        = ['rows' => [], 'inserts' => [], 'results' => []];
            $GLOBALS['_cp_test_screen_id'] = '';
            $_GET                          = [];
            $_POST                         = [];
        }

        /**
         * Dos cuentas (Telegram y X) con un canal de mentira cada una, para ver a quién llega
         * cada envío y con qué texto.
         *
         * @return array<int, string>
         */
        private function cuentas(): array
        {
            $canales = [];
            $ids     = [];

            // Con las credenciales que pide cada canal de verdad: si no, la cuenta no está
            // «disponible» y la regla de a quién va la entrada la deja fuera.
            $credenciales = [
                'telegram' => [
                    'convoca_publisher_telegram_token'   => 'TOKEN',
                    'convoca_publisher_telegram_chat_id' => '-100',
                ],
                'twitter' => ['convoca_publisher_twitter_bearer_token' => 'BEARER'],
            ];

            foreach ([['telegram', 'Telegram'], ['twitter', 'X']] as [$red, $nombre]) {
                $perfil = Profile_Store::create($red, $nombre . ' — Prueba', $credenciales[$red]);

                $this->assertIsArray($perfil, 'No se pudo crear la cuenta de prueba.');

                $id       = (string) $perfil['id'];
                $ids[]    = $id;
                $canales[$id] = new FakeChannel($id, $red, $nombre);

            }

            $this->canales = $canales;
            Publisher::init($canales);

            return $ids;
        }

        private function entrada(int $id = 77, array $meta = []): \WP_Post
        {
            $GLOBALS['_cp_test_posts'][]       = $id;
            $GLOBALS['_cp_test_titles'][$id]   = 'Asamblea de socios';
            $GLOBALS['_cp_test_postmeta'][$id] = $meta;

            $post             = new \WP_Post();
            $post->ID         = $id;
            $post->post_type  = 'post';
            $post->post_title = 'Asamblea de socios';

            return $post;
        }

        // ── De dónde sale el mensaje ────────────────────────────────────────

        public function testElMensajeDeLaEntradaMandaSobreLaPlantillaDeLaCuenta(): void
        {
            $ids = $this->cuentas();
            $this->entrada(77, [Publisher::MESSAGE_META => 'Propio: {title} → {url}']);

            $mensaje = (new Publisher(Plugin::accounts()))->get_channel_message(77, $ids[0]);

            $this->assertStringStartsWith('Propio: Asamblea de socios', $mensaje, 'Lo que se escribe para la entrada va primero.');
            $this->assertStringContainsString('https://', $mensaje, 'Y las variables se siguen sustituyendo.');
        }

        public function testSinMensajePropioMandaLaPlantillaDeLaCuenta(): void
        {
            $perfil = Profile_Store::create('telegram', 'Telegram — Prueba', ['convoca_publisher_telegram_token' => 'T'], 'Cuenta: {title}');
            $this->assertIsArray($perfil);

            $publicador = new Publisher(Plugin::accounts());
            $this->entrada();

            $this->assertStringStartsWith('Cuenta: Asamblea de socios', $publicador->get_channel_message(77, (string) $perfil['id']));
        }

        public function testLoQueSeMandaParaEsteEnvioMandaSobreTodo(): void
        {
            $ids = $this->cuentas();
            $this->entrada(77, [Publisher::MESSAGE_META => 'La de la entrada']);

            $publicador = new Publisher($this->canales);

            $this->assertStringStartsWith('La de la entrada', $publicador->get_channel_message(77, $ids[0]), 'Sin más, la de la entrada.');
            $this->assertStringStartsWith(
                'Solo para este envío',
                $publicador->preview_message(77, $ids[0], 'Solo para este envío: {title}'),
                'Y cuando se manda algo para un envío concreto, manda eso.'
            );
        }

        // ── Una cuenta concreta ─────────────────────────────────────────────

        public function testCompartirEnUnaCuentaNoTocaLasDemas(): void
        {
            $ids = $this->cuentas();
            $this->entrada();
            $GLOBALS['_cp_test_envios'] = [];

            $resultado = (new Publisher($this->canales))->publish_to_accounts(77, [$ids[0]], true, true);

            $this->assertArrayHasKey($ids[0], $resultado, 'Se envía a la cuenta pedida.');
            $this->assertArrayNotHasKey($ids[1], $resultado, 'Y a ninguna otra.');

            $cuentas = array_filter(array_keys($resultado), static fn(string $clave): bool => '_' !== $clave[0]);

            $this->assertCount(1, $cuentas);
            $this->assertTrue(!empty($resultado[$ids[0]]['success']));
        }

        public function testPublicarSinDecirCuentaVaALasQueTocan(): void
        {
            $ids = $this->cuentas();
            $this->entrada();

            $resultado = (new Publisher($this->canales))->publish_post(77, true, true);
            $cuentas   = array_filter(array_keys($resultado), static fn(string $clave): bool => '_' !== $clave[0]);

            $this->assertCount(2, $cuentas, 'Sin cuenta concreta, a todas las marcadas.');
        }

        public function testUnaCuentaDesmarcadaNoRecibeElCompartirGeneral(): void
        {
            $ids = $this->cuentas();
            $this->entrada(77, ['_convoca_publisher_disabled_channels' => [$ids[1]]]);

            $resultado = (new Publisher($this->canales))->publish_post(77, true, true);

            $this->assertArrayHasKey($ids[0], $resultado);
            $this->assertArrayNotHasKey($ids[1], $resultado, 'La cuenta desmarcada no recibe nada.');
        }

        // ── Lo que admite la red ────────────────────────────────────────────

        public function testLoQueNoCabeSeRecortaYQuedaDicho(): void
        {
            $ids = $this->cuentas();
            // La segunda cuenta es X (280): un mensaje de 400 caracteres no cabe.
            $this->entrada(77, [Publisher::MESSAGE_META => str_repeat('Asamblea de socios y taller de huerto. ', 12)]);
            $resultado = (new Publisher($this->canales))->publish_to_accounts(77, [$ids[1]], true, true);
            $enviado   = $this->canales[$ids[1]]->sent[0]['message'] ?? '';

            $this->assertLessThanOrEqual(280, mb_strlen($enviado), 'Lo que se envía cabe.');
            $this->assertArrayHasKey('trimmed', $resultado[$ids[1]], 'Y queda anotado que se recortó.');
            $this->assertNotEmpty($resultado['_warnings'] ?? [], 'Con su aviso, que es lo que el usuario ve.');
        }

        public function testLoQueCabeNoSeToca(): void
        {
            $ids = $this->cuentas();
            $this->entrada();

            $resultado = (new Publisher(Plugin::accounts()))->publish_to_accounts(77, [$ids[0]], true, true);

            $this->assertArrayNotHasKey('trimmed', $resultado[$ids[0]]);
        }

        // ── La caja del editor ──────────────────────────────────────────────

        private function caja(int $post_id = 77): string
        {
            $post             = new \WP_Post();
            $post->ID         = $post_id;
            $post->post_type  = 'post';
            $post->post_title = 'Asamblea de socios';

            $GLOBALS['_cp_test_posts'][]     = $post_id;
            $GLOBALS['_cp_test_titles'][$post_id] = 'Asamblea de socios';
            $GLOBALS['_cp_test_post']        = $post;

            ob_start();
            Metabox::render($post);

            return (string) ob_get_clean();
        }

        public function testLaCajaTraeElMensajeDeLaEntrada(): void
        {
            $this->cuentas();
            $this->entrada();

            $html = $this->caja();

            $this->assertStringContainsString('name="convoca_publisher_message"', $html, 'Se puede escribir el mensaje de esta entrada.');
            $this->assertStringContainsString('{title}', $html, 'Y dice qué variables valen.');
        }

        public function testCadaCuentaTraeSuRecuentoYSuVistaPrevia(): void
        {
            $this->cuentas();
            $this->entrada();

            $html = $this->caja();

            $this->assertSame(2, substr_count($html, 'data-cp-resumen'), 'Una tarjeta por cuenta.');
            $this->assertStringContainsString('data-cp-red="twitter"', $html);
            $this->assertStringContainsString('/ 280', $html, 'Con el tope de esa red.');
            $this->assertStringContainsString('/ 4096', $html, 'Y el de la otra.');
            $this->assertSame(2, substr_count($html, 'data-cp-previa'), 'Cada una con su vista previa.');
            $this->assertSame(2, substr_count($html, 'data-cp-contador>'), 'El contador que el JS actualiza mientras se escribe.');
        }

        public function testCadaCuentaTraeSuBotonDeCompartir(): void
        {
            $this->cuentas();
            $this->entrada();

            $html = $this->caja();

            $this->assertSame(2, substr_count($html, 'cp-compartir'));
            $this->assertStringContainsString('Compartir ahora en esta cuenta', $html);
        }

        public function testSinCuentasLaCajaLoDiceYNoMiente(): void
        {
            $this->entrada();

            $html = $this->caja();

            $this->assertStringContainsString('Todavía no hay ninguna cuenta configurada', $html);
            $this->assertStringNotContainsString('cp-compartir', $html, 'Sin cuentas no se ofrece compartir.');
        }

        public function testLaCajaNoLlevaJavaScriptIncrustado(): void
        {
            $this->cuentas();
            $this->entrada();

            $html = $this->caja();

            $this->assertStringNotContainsString('<script', $html, 'El JS vive en un fichero.');
            $this->assertStringNotContainsString('style="', $html, 'Y el estilo, en la hoja.');
        }

        // ── Desde el listado de entradas ────────────────────────────────────

        public function testElListadoOfreceCompartirAhora(): void
        {
            $this->cuentas();

            $post             = new \WP_Post();
            $post->ID         = 77;
            $post->post_type  = 'post';

            $acciones = Admin::row_action([], $post);

            $this->assertArrayHasKey('convoca_compartir', $acciones);
            $this->assertStringContainsString('cp_share_now', $acciones['convoca_compartir']);
            $this->assertStringContainsString('Compartir ahora', $acciones['convoca_compartir']);
        }

        public function testSinCuentasElListadoNoOfreceCompartir(): void
        {
            $post             = new \WP_Post();
            $post->ID         = 77;
            $post->post_type  = 'post';

            $this->assertArrayNotHasKey('convoca_compartir', Admin::row_action([], $post));
        }
    }
}
