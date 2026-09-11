<?php

/**
 * Que un envío no se pierda en silencio.
 *
 * Tres agujeros que se tapaban entre ellos:
 * - El programado **borraba la marca aunque el envío hubiera fallado**, así que ese post ya no
 *   lo volvía a intentar el cron: desaparecía de la cola y nadie se enteraba.
 * - El cron de recuperación **abandonaba a quien hubiera fallado más de una vez**, que es
 *   justo el que necesita otra vuelta.
 * - Si el cron no llegaba a correr (WordPress lo dispara el tráfico), lo programado se quedaba
 *   atrás sin que apareciera en ninguna parte.
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Admin;
    use ConvocaPublisher\Notifications;
    use ConvocaPublisher\Plugin;
    use ConvocaPublisher\Profile_Store;
    use ConvocaPublisher\Publisher;
    use ConvocaPublisher\Queue;
    use ConvocaPublisher\Scheduler;
    use ConvocaPublisher\Tests\Support\FakeChannel;
    use PHPUnit\Framework\TestCase;

    final class ReliabilityTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            \cp_test_reset();

            $_GET = [];
        }

        /**
         * Una cuenta que falla siempre, otra que funciona, y una entrada programada.
         *
         * @return array{0: string, 1: string, 2: int}
         */
        private function escenario(bool $falla = true, int $cuando = 0): array
        {
            $canales = [];

            foreach ([['telegram', 'Telegram', !$falla], ['twitter', 'X', !$falla]] as [$red, $nombre, $va_bien]) {
                $perfil = Profile_Store::create($red, $nombre, [
                    'convoca_publisher_' . $red . '_token' => 'T',
                    'convoca_publisher_telegram_chat_id'   => '-100',
                    'convoca_publisher_twitter_bearer_token' => 'B',
                ]);

                $this->assertIsArray($perfil);
                $id = (string) $perfil['id'];

                $canales[$id] = new FakeChannel($id, $red, $nombre, $va_bien);

            }

            Publisher::init($canales);

            $post_id = 77;

            $GLOBALS['_cp_test_posts'][]                       = $post_id;
            $GLOBALS['_cp_test_titles'][$post_id]              = 'Asamblea de socios';
            $GLOBALS['_cp_test_postmeta'][$post_id]            = [
                Queue::SCHEDULE_META => $cuando,
            ];

            return [array_key_first($canales), array_key_last($canales), $post_id];
        }

        /** El estado de una entrada, tal y como lo ve el cron. */
        private function meta(int $post_id, string $clave): mixed
        {
            return $GLOBALS['_cp_test_postmeta'][$post_id][$clave] ?? null;
        }

        // ── Lo que se quedó atrás ───────────────────────────────────────────

        public function testUnProgramadoQueYaPasoYNoSalioApareceComoAtrasado(): void
        {
            $this->escenario(false, time() - 3600);

            $atrasados = Queue::overdue();

            $this->assertCount(1, $atrasados, 'Una fila por entrada, aunque tenga varias cuentas.');
            $this->assertSame(77, (int) $atrasados[0]['post_id']);
            $this->assertSame(0, (int) $atrasados[0]['attempts']);
        }

        public function testLoQueTodaviaNoTocaNoEsUnAtraso(): void
        {
            $this->escenario(false, time() + 3600);

            $this->assertSame([], Queue::overdue());
        }

        public function testLoQueYaSalioNoEsUnAtraso(): void
        {
            [, , $post_id] = $this->escenario(false, time() - 3600);

            $GLOBALS['_cp_test_postmeta'][$post_id]['_convoca_publisher_published'] = true;

            $this->assertSame([], Queue::overdue(), 'Ya salió: no hay nada que recuperar.');
        }

        public function testUnAtrasadoSePuedeEnviarAMano(): void
        {
            [$id] = $this->escenario(false, time() - 3600);

            $resultado = (new Publisher(Plugin::accounts()))->publish_to_accounts(77, [], true, true);

            $this->assertArrayHasKey($id, $resultado);
            $this->assertNotEmpty($resultado[$id]['error'], 'La red sigue diciendo que no, y eso se ve.');
        }

        // ── Insistir, pero no para siempre ──────────────────────────────────

        public function testCadaIntentoQuedaContado(): void
        {
            [, , $post_id] = $this->escenario();

            Queue::count_attempt($post_id);
            Queue::count_attempt($post_id);

            $this->assertSame(2, Queue::attempts($post_id));
        }

        public function testAlAgotarLosIntentosSeDejaDeInsistirPeroQuedaAVista(): void
        {
            [, , $post_id] = $this->escenario();

            for ($i = 0; $i < Queue::MAX_ATTEMPTS; ++$i) {
                Queue::count_attempt($post_id);
            }

            Queue::give_up($post_id);

            $this->assertNull($this->meta($post_id, Queue::SCHEDULE_META), 'El cron deja de darle vueltas.');
            $this->assertNotNull($this->meta($post_id, Queue::HELP_META), 'Pero queda anotado para que la cola lo enseñe.');
            $this->assertContains($post_id, Queue::needs_help());
        }

        public function testVolverALaNormalidadBorraLosIntentos(): void
        {
            [, , $post_id] = $this->escenario();

            Queue::count_attempt($post_id);
            Queue::give_up($post_id);
            Queue::forget_attempts($post_id);

            $this->assertSame(0, Queue::attempts($post_id));
            $this->assertNotContains($post_id, Queue::needs_help());
        }

        // ── El cron ────────────────────────────────────────────────────────

        /**
         * Deja el cron con una entrada vencida que le toca.
         */
        private function cronConVencida(int $post_id): void
        {
            $GLOBALS['_cp_test_db']['results'] = [
                (object) ['post_id' => $post_id, 'meta_value' => (string) (time() - 60)],
            ];
            $GLOBALS['_cp_test_options']['convoca_publisher_queue_last_sent'] = time() - 7200;
        }

        public function testSiNoSaleEnNingunaCuentaNoSePierdeLaMarca(): void
        {
            [, , $post_id] = $this->escenario(true);
            $this->cronConVencida($post_id);

            Scheduler::publish_scheduled();

            $this->assertNotNull($this->meta($post_id, Queue::SCHEDULE_META), 'Se vuelve a intentar en el siguiente turno.');
            $this->assertSame(1, Queue::attempts($post_id));
        }

        public function testSiSaleSeLimpiaYSeDejaDeInsistir(): void
        {
            [$id, , $post_id] = $this->escenario(false);

            $this->cronConVencida($post_id);
            Scheduler::publish_scheduled();

            $this->assertNull($this->meta($post_id, Queue::SCHEDULE_META), 'Ya salió: no hay nada que reintentar.');
            $this->assertSame(0, Queue::attempts($post_id), 'Y no arrastra intentos.');
            $this->assertNotNull($id);
        }

        public function testAlAgotarLosIntentosElCronPideAyuda(): void
        {
            [, , $post_id] = $this->escenario(true);
            $GLOBALS['_cp_test_options']['admin_email']                = 'quien.administra@example.com';
            $GLOBALS['_cp_test_postmeta'][$post_id][Queue::ATTEMPTS_META] = Queue::MAX_ATTEMPTS - 1;

            $this->cronConVencida($post_id);
            Scheduler::publish_scheduled();

            $this->assertNull($this->meta($post_id, Queue::SCHEDULE_META), 'Se deja de insistir.');
            $this->assertNotNull($this->meta($post_id, Queue::HELP_META));
            $this->assertCount(1, $GLOBALS['_cp_test_mail'], 'Y se avisa por correo, que llega aunque nadie mire.');
        }

        // ── El aviso por correo ────────────────────────────────────────────

        public function testElAvisoPorCorreoVaAQuienAdministraElSitio(): void
        {
            [, , $post_id] = $this->escenario();

            $GLOBALS['_cp_test_options']['admin_email'] = 'quien.administra@example.com';

            Notifications::ask_for_help($post_id, 5);

            $this->assertCount(1, $GLOBALS['_cp_test_mail']);
            $this->assertSame('quien.administra@example.com', $GLOBALS['_cp_test_mail'][0]['to']);
            $this->assertStringContainsString('Asamblea de socios', $GLOBALS['_cp_test_mail'][0]['message'], 'Dice de qué entrada se trata.');
            $this->assertStringContainsString('5', $GLOBALS['_cp_test_mail'][0]['message'], 'Y cuántas veces se intentó.');
        }

        public function testElAvisoPorCorreoTambienDejaRastroEnLaPantalla(): void
        {
            [, , $post_id] = $this->escenario();

            Notifications::ask_for_help($post_id, 5);

            $guardado = get_option('convoca_publisher_needs_help', []);

            $this->assertArrayHasKey((string) $post_id, $guardado, 'Queda anotado para la cola.');
        }

        public function testSiSeApaganLosAvisosNoSeEscribe(): void
        {
            [, , $post_id] = $this->escenario();
            $GLOBALS['_cp_test_options']['convoca_publisher_email_alerts'] = '';

            Notifications::ask_for_help($post_id, 5);

            $this->assertSame([], $GLOBALS['_cp_test_mail']);
        }

        // ── La pantalla de la cola ─────────────────────────────────────────

        private function cola(): string
        {
            $_GET = ['page' => 'convoca-publisher', 'tab' => 'queue'];

            ob_start();
            Admin::render_page();

            return (string) ob_get_clean();
        }

        public function testLaColaEnseniaLoQueSeQuedoAtras(): void
        {
            $this->escenario(false, time() - 3600);

            $html = $this->cola();

            $this->assertStringContainsString('Se quedó atrás', $html);
            $this->assertStringContainsString('Enviar todo lo atrasado', $html);
            $this->assertStringContainsString('Enviar ahora', $html);
            $this->assertStringContainsString('Asamblea de socios', $html);
        }

        public function testSinNadaAtrasadoNoSeEnseniaLaSeccion(): void
        {
            $this->escenario(false, time() + 3600);

            $this->assertStringNotContainsString('Se quedó atrás', $this->cola());
        }
    }
}
