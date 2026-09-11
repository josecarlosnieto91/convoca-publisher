<?php

/**
 * La pantalla de la cola: calendario (mes y semana), lista y reprogramar.
 *
 * Lo que se fija aquí: que el calendario pinte los días y los envíos de cada día, que los
 * envíos que ya salieron no se puedan arrastrar (no hay nada que mover), que la lista
 * enseñe lo que espera turno y lo que falló, y que la conversión de «qué día y qué hora»
 * del formulario a un sello de tiempo del sitio sea la correcta (esto último es lo que
 * rompe las fechas cuando se toca sin querer).
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Admin;
    use ConvocaPublisher\Plugin;
    use ConvocaPublisher\Profile_Store;
    use ConvocaPublisher\Queue;
    use PHPUnit\Framework\TestCase;

    final class QueueAdminTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            $GLOBALS['_cp_test_options']  = [];
            $GLOBALS['_cp_test_postmeta'] = [];
            $GLOBALS['_cp_test_posts']    = [];
            $GLOBALS['_cp_test_titles']   = [];
            $GLOBALS['_cp_test_db']       = ['rows' => [], 'inserts' => [], 'results' => []];
            $_GET                         = [];
        }

        /**
         * Una cuenta lista para enviar y una entrada programada para dentro de unos días.
         *
         * @return array{0: int, 1: int} El id de la entrada y el sello de su envío.
         */
        private function escenario(int $dias = 3): array
        {
            Profile_Store::create('telegram', 'Telegram — Centro Social', [
                'convoca_publisher_telegram_token'   => 'TOKEN',
                'convoca_publisher_telegram_chat_id' => '-100',
            ]);

            $cuando = time() + ($dias * DAY_IN_SECONDS);
            $post   = 77;

            $GLOBALS['_cp_test_posts'][]                              = $post;
            $GLOBALS['_cp_test_titles'][$post]                        = 'Fiesta del centro';
            $GLOBALS['_cp_test_postmeta'][$post][Queue::SCHEDULE_META] = $cuando;

            return [$post, $cuando];
        }

        private function render(array $get): string
        {
            $_GET = array_merge(['page' => 'convoca-publisher', 'tab' => 'queue'], $get);

            ob_start();
            Admin::render_page();

            return (string) ob_get_clean();
        }

        // ── El calendario ───────────────────────────────────────────────────

        public function testElCalendarioDelMesPintaElEnvioEnSuDia(): void
        {
            [$post, $cuando] = $this->escenario();

            $html = $this->render([]);

            $this->assertStringContainsString('cp-cal', $html);
            $this->assertSame(42, substr_count($html, 'data-cp-dia="'), 'Seis semanas de celdas, como cualquier calendario de mes.');
            $this->assertStringContainsString('data-cp-dia="' . wp_date('Y-m-d', $cuando) . '"', $html, 'El día del envío está en la rejilla.');
            $this->assertStringContainsString('data-cp-envio="schedule:' . $post . '"', $html);
            $this->assertStringContainsString('draggable="true"', $html, 'Lo que no ha salido se puede arrastrar.');
            $this->assertStringContainsString('Fiesta del centro', $html);
            $this->assertStringContainsString('Telegram — Centro Social', $html);
            $this->assertStringContainsString('Recolocar ahora', $html);
        }

        public function testLaVistaDeSemanaPintaSieteDias(): void
        {
            $this->escenario();

            $html = $this->render(['vista' => 'semana']);

            $this->assertStringContainsString('Semana del ', $html);
            $this->assertSame(7, substr_count($html, 'data-cp-dia="'), 'Una celda por día de la semana.');
        }

        public function testLosEnviosQueYaSalieronNoSeArrastran(): void
        {
            $this->escenario();

            update_option('convoca_publisher_publish_log', [
                [
                    'title'    => 'Ya salió',
                    'channel'  => 'Telegram — Centro Social',
                    'success'  => true,
                    'time'     => wp_date('Y-m-d H:i:s'),
                    'response' => 'ok',
                ],
            ]);

            $html = $this->render([]);

            $this->assertStringContainsString('cp-cal__envio--hecho', $html, 'Lo que ya salió se distingue.');
            $this->assertStringContainsString('draggable="false"', $html, 'Y no se puede arrastrar: no hay nada que mover.');
        }

        public function testUnFalloSeVeComoFallo(): void
        {
            $this->escenario();

            $GLOBALS['_cp_test_db']['results'] = [
                (object) [
                    'id' => 5, 'post_id' => 77, 'channel' => 'telegram', 'payload' => '',
                    'error_text' => 'HTTP 500', 'attempts' => 3, 'status' => 'failed',
                    'last_attempt' => wp_date('Y-m-d H:i:s', time() - 3600),
                    'next_attempt' => wp_date('Y-m-d H:i:s', time() - 1800),
                    'created_at'   => wp_date('Y-m-d H:i:s', time() - 7200),
                ],
            ];

            $html = $this->render([]);

            $this->assertStringContainsString('Atascado', $html);
            $this->assertStringContainsString('HTTP 500', $html);
            $this->assertStringContainsString('data-cp-envio="retry:5"', $html);
        }

        public function testLaListaTraeReprogramarYQuitar(): void
        {
            $this->escenario();

            $html = $this->render([]);

            $this->assertStringContainsString('Lo que espera turno', $html);
            $this->assertStringContainsString('Lo último que salió', $html);
            $this->assertStringContainsString('name="cp_cuando"', $html, 'Se puede cambiar la hora desde la lista.');
            $this->assertStringContainsString('cp_queue_cancel', $html, 'Y quitarlo de la cola.');
        }

        public function testLaPantallaDeLaColaNoUsaEstilosSueltos(): void
        {
            $this->escenario();

            $html = $this->render([]);

            $this->assertStringNotContainsString('style="', $html);
            $this->assertStringNotContainsString('Ajustes', $html);
        }

        // ── Qué día y qué hora (lo que rompe las fechas) ────────────────────

        public function testUnDiaDelCalendarioSeConvierteEnSuMananaDelSitio(): void
        {
            $sello = Admin::queue_parse_when('2026-09-20', '');

            $this->assertSame('2026-09-20 09:00', wp_date('Y-m-d H:i', $sello));
        }

        public function testUnaFechaYHoraDeLaListaSeRespeta(): void
        {
            $sello = Admin::queue_parse_when('', '2026-09-20T18:30');

            $this->assertSame('2026-09-20 18:30', wp_date('Y-m-d H:i', $sello));
        }

        public function testSinDatosNoSeInventaUnaFecha(): void
        {
            $this->assertSame(0, Admin::queue_parse_when('', ''));
            $this->assertSame(0, Admin::queue_parse_when('', 'mañana por la tarde'));
            $this->assertSame(0, Admin::queue_parse_when('próximamente', ''));
        }
    }
}

namespace {

    if (!function_exists('convoca_publisher')) {
        /**
         * Doble del acceso al plugin para renderizar la pantalla sin arrancar WordPress.
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
