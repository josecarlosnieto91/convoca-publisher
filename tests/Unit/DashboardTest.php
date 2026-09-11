<?php

/**
 * El widget del escritorio: lo que va a salir, lo que se ha atascado y lo último que salió.
 *
 * Es lo que se ve sin entrar a buscar. Lo que se fija aquí: que enseñe lo que de verdad está
 * programado (y no lo de dentro de un mes), que avise cuando algo se ha quedado parado, y que
 * quien no administra el sitio no lo vea.
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Dashboard;
    use ConvocaPublisher\Profile_Store;
    use ConvocaPublisher\Queue;
    use PHPUnit\Framework\TestCase;

    final class DashboardTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            \cp_test_reset();

            $_GET = [];
        }

        private function cuenta(): void
        {
            $perfil = Profile_Store::create('telegram', 'Telegram — Centro Social', [
                'convoca_publisher_telegram_token'   => 'TOKEN',
                'convoca_publisher_telegram_chat_id' => '-100',
            ]);

            $this->assertIsArray($perfil);
        }

        private function programar(int $post_id, int $cuando, string $titulo = ''): void
        {
            $GLOBALS['_cp_test_posts'][]                 = $post_id;
            $GLOBALS['_cp_test_titles'][$post_id]        = '' !== $titulo ? $titulo : 'Entrada ' . $post_id;
            $GLOBALS['_cp_test_postmeta'][$post_id]      = [Queue::SCHEDULE_META => $cuando];
        }

        private function pintar(): string
        {
            ob_start();
            Dashboard::render();

            return (string) ob_get_clean();
        }

        // ── Lo siguiente ────────────────────────────────────────────────────

        public function testEnseniaLoQueVaASalirYEnQueOrden(): void
        {
            $this->cuenta();
            $this->programar(11, time() + (2 * DAY_IN_SECONDS), 'La de pasado mañana');
            $this->programar(12, time() + (6 * HOUR_IN_SECONDS), 'La de esta tarde');

            $proximos = Dashboard::next_up();

            $this->assertCount(2, $proximos);
            $this->assertSame('La de esta tarde', $proximos[0]['title'], 'Primero lo que sale antes.');
            $this->assertSame('La de pasado mañana', $proximos[1]['title']);
        }

        public function testLoQueSaleDentroDeUnMesNoEsLoSiguiente(): void
        {
            $this->cuenta();
            $this->programar(11, time() + (30 * DAY_IN_SECONDS));

            $this->assertSame([], Dashboard::next_up(), 'La ventana es la semana que viene, no el mes.');
        }

        public function testLoQueYaPasoNoCuentaComoLoSiguiente(): void
        {
            $this->cuenta();
            $this->programar(11, time() - HOUR_IN_SECONDS);

            $this->assertSame([], Dashboard::next_up());
        }

        public function testUnEnvioConReintentoNoSaleDosVeces(): void
        {
            $this->cuenta();
            $this->programar(11, time() + HOUR_IN_SECONDS, 'Con reintento');

            // El mismo envío, además con un reintento vivo (la red falló y se reintenta).
            $GLOBALS['_cp_test_db']['results'] = [
                (object) [
                    'id' => 3, 'post_id' => 11, 'channel' => array_key_first(\ConvocaPublisher\Plugin::accounts()),
                    'payload' => '', 'error_text' => 'la red dijo que no', 'attempts' => 1,
                    'status' => 'pending', 'last_attempt' => wp_date('Y-m-d H:i:s'),
                    'next_attempt' => wp_date('Y-m-d H:i:s', time() + HOUR_IN_SECONDS),
                    'created_at' => wp_date('Y-m-d H:i:s'),
                ],
            ];

            $this->assertCount(1, Dashboard::next_up(), 'Es el mismo envío: no puede aparecer dos veces.');
        }

        public function testDosCuentasDeLaMismaEntradaSonDosEnvios(): void
        {
            // No es lo mismo deduplicar un envío repetido que juntar dos cuentas distintas: la
            // misma entrada en la página y en el grupo de Facebook son dos envíos, y los dos
            // tienen que aparecer.
            foreach ([['telegram', 'Telegram — Canal'], ['mastodon', 'Mastodon']] as [$red, $nombre]) {
                $perfil = Profile_Store::create($red, $nombre, [
                    'convoca_publisher_' . $red . '_token' => 'T',
                    'convoca_publisher_telegram_chat_id'   => '-100',
                    'convoca_publisher_mastodon_server'    => 'https://mastodon.social',
                ]);

                $this->assertIsArray($perfil);
            }

            $this->programar(11, time() + HOUR_IN_SECONDS, 'Para dos cuentas');

            $this->assertCount(2, Dashboard::next_up(), 'Una entrada, dos cuentas: dos envíos.');
        }

        // ── Lo atascado ─────────────────────────────────────────────────────

        public function testLoAtrasadoYSinInsistirSaleJunto(): void
        {
            $this->cuenta();
            $this->programar(11, time() - (2 * HOUR_IN_SECONDS), 'Atrasada');
            $this->programar(12, time() - (3 * HOUR_IN_SECONDS), 'Sin insistir');

            Queue::give_up(12);   // se dejó de insistir: queda anotada (y así se queda)

            $parados   = Dashboard::stuck();
            $titulos   = array_column($parados, 'title');

            $this->assertContains('Atrasada', $titulos, 'Lo atrasado tiene que verse.');
            $this->assertContains('Sin insistir', $titulos, 'Y lo que ya no se reintenta solo, también.');
        }

        public function testSinNadaParadoNoHayAviso(): void
        {
            $this->cuenta();
            $this->programar(11, time() + DAY_IN_SECONDS);

            $html = $this->pintar();

            $this->assertStringNotContainsString('no ha salido', $html);
            $this->assertStringContainsString('Lo siguiente', $html);
        }

        // ── Lo que se pinta ─────────────────────────────────────────────────

        public function testCuandoAlgoSeQuedaParadoElEscritorioLoDiceYOfreceIr(): void
        {
            $this->cuenta();
            $this->programar(11, time() - (2 * HOUR_IN_SECONDS), 'Asamblea de socios');

            $html = $this->pintar();

            $this->assertStringContainsString('1 envío no ha salido', $html);
            $this->assertStringContainsString('Asamblea de socios', $html);
            $this->assertStringContainsString('Ver qué pasó y reintentar', $html);
            $this->assertStringContainsString('tab=queue', $html, 'Y lleva a la cola, que es donde se arregla.');
        }

        public function testUnAvisoDelPropioPluginNoSeLeeComoUnFallo(): void
        {
            $this->cuenta();

            update_option('convoca_publisher_publish_log', [
                [
                    'title'    => 'Entrada sin imagen destacada',
                    'channel'  => 'VALIDACIÓN',
                    'success'  => false,
                    'time'     => wp_date('Y-m-d H:i:s'),
                    'response' => 'No hay imagen destacada.',
                ],
            ]);

            $this->assertSame([], Queue::sent_entries(), 'Las filas de validación no son envíos.');
            $this->assertStringNotContainsString('❌', $this->pintar(), 'Y no se pintan como fallo de la red.');
        }

        public function testLosAvisosNoSeComenElTurnoDeLosEnvios(): void
        {
            $this->cuenta();

            // Tres avisos del plugin y dos envíos de verdad, pidiendo dos: los dos envíos.
            $avisos = [];
            for ($i = 0; $i < 3; ++$i) {
                $avisos[] = ['title' => 'Aviso ' . $i, 'channel' => 'VALIDACIÓN', 'success' => false, 'time' => wp_date('Y-m-d H:i:s'), 'response' => 'sin imagen'];
            }
            $avisos[] = ['title' => 'Salió bien', 'channel' => 'Telegram', 'success' => true, 'time' => wp_date('Y-m-d H:i:s'), 'response' => 'ok'];
            $avisos[] = ['title' => 'Salió mal', 'channel' => 'Telegram', 'success' => false, 'time' => wp_date('Y-m-d H:i:s'), 'response' => 'nope'];

            update_option('convoca_publisher_publish_log', $avisos);

            $salidas = Queue::sent_entries(2);

            $this->assertCount(2, $salidas, 'Los avisos no ocupan el sitio de los envíos.');
            $this->assertSame(['Salió mal', 'Salió bien'], array_column($salidas, 'title'), 'Y salen los dos últimos, del más nuevo al más viejo.');
        }

        public function testSinNadaProgramadoLoDiceEnUnaLinea(): void
        {
            $this->cuenta();

            $this->assertStringContainsString('No hay nada programado', $this->pintar());
        }

        public function testLoUltimoQueSalioSeVeConSuResultado(): void
        {
            $this->cuenta();

            update_option('convoca_publisher_publish_log', [
                [
                    'title'    => 'Taller de huerto',
                    'channel'  => 'Telegram — Centro Social',
                    'success'  => true,
                    'time'     => wp_date('Y-m-d H:i:s'),
                    'response' => 'ok',
                ],
                [
                    'title'    => 'Fiesta del centro',
                    'channel'  => 'Telegram — Centro Social',
                    'success'  => false,
                    'time'     => wp_date('Y-m-d H:i:s'),
                    'response' => 'la red dijo que no',
                ],
            ]);

            $html = $this->pintar();

            $this->assertStringContainsString('Taller de huerto', $html);
            $this->assertStringContainsString('✅', $html, 'Lo que salió bien se distingue.');
            $this->assertStringContainsString('❌', $html, 'Y lo que no, también.');
        }

        public function testQuienNoAdministraElSitioNoVeElWidget(): void
        {
            $GLOBALS['_cp_test_can_manage'] = false;

            Dashboard::register();

            $this->assertSame([], $GLOBALS['_cp_test_widgets'], 'El escritorio no es para todos.');
        }

        public function testElWidgetSeRegistraParaQuienAdministra(): void
        {
            Dashboard::register();

            $this->assertArrayHasKey(Dashboard::WIDGET_ID, $GLOBALS['_cp_test_widgets']);
            $this->assertSame('Convoca Publisher', $GLOBALS['_cp_test_widgets'][Dashboard::WIDGET_ID]);
        }
    }
}
