<?php

/**
 * La cola: qué sale, cuándo y con qué espaciado.
 *
 * Lo que se fija aquí es la regla que evita que salgan cinco publicaciones en el mismo
 * minuto: los envíos que caen dentro del intervalo se recolocan solos, y la publicación
 * programada no dispara nada si acaba de salir otra cosa. El recolocado es una función
 * pura (`Queue::spaced()`), que es justo lo que se puede probar sin montar medio
 * WordPress.
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Plugin;
    use ConvocaPublisher\Profile_Store;
    use ConvocaPublisher\Publisher;
    use ConvocaPublisher\Queue;
    use PHPUnit\Framework\TestCase;

    final class QueueTest extends TestCase
    {
        protected function setUp(): void
        {
            \cp_test_reset();

            parent::setUp();

            $GLOBALS['_cp_test_options']  = [];
            $GLOBALS['_cp_test_postmeta'] = [];
            $GLOBALS['_cp_test_posts']    = [];
            $GLOBALS['_cp_test_titles']   = [];
            $GLOBALS['_cp_test_db']       = ['rows' => [], 'inserts' => []];
        }

        /**
         * Dos cuentas de Telegram listas para enviar.
         *
         * @return array<int, string> Sus ids.
         */
        private function dosCuentas(): array
        {
            $ids = [];

            foreach (['Telegram — Centro Social', 'Telegram — Socios'] as $i => $nombre) {
                $cuenta = Profile_Store::create('telegram', $nombre, [
                    'convoca_publisher_telegram_token'   => 'TOKEN-' . $i,
                    'convoca_publisher_telegram_chat_id' => '-' . (100 + $i),
                ]);

                $this->assertIsArray($cuenta);
                $ids[] = $cuenta['id'];
            }

            return $ids;
        }

        private function postProgramado(int $id, int $cuando, string $titulo = 'Entrada de prueba', array $desactivadas = []): void
        {
            $GLOBALS['_cp_test_posts'][]                = $id;
            $GLOBALS['_cp_test_titles'][$id]            = $titulo;
            $GLOBALS['_cp_test_postmeta'][$id][Queue::SCHEDULE_META] = $cuando;

            if ([] !== $desactivadas) {
                $GLOBALS['_cp_test_postmeta'][$id]['_convoca_publisher_disabled_channels'] = $desactivadas;
            }
        }

        /**
         * @param array<int, array<string, mixed>> $entries
         *
         * @return array<int, int>
         */
        private function horas(array $entries): array
        {
            return array_map(static fn(array $e): int => (int) $e['time'], $entries);
        }

        // ── El espaciado (la regla de verdad) ───────────────────────────────

        public function testLoQueCaeDentroDelIntervaloSeRecolocaSolo(): void
        {
            $base = 1_800_000_000;

            $entradas = Queue::spaced([
                ['kind' => 'schedule', 'post_id' => 1, 'time' => $base],
                ['kind' => 'schedule', 'post_id' => 2, 'time' => $base],
                ['kind' => 'schedule', 'post_id' => 3, 'time' => $base + 60],
            ], 1800);

            $this->assertSame([$base, $base + 1800, $base + 3600], $this->horas($entradas));
            $this->assertFalse($entradas[0]['moved'], 'El primero no se mueve.');
            $this->assertTrue($entradas[1]['moved']);
            $this->assertTrue($entradas[2]['moved']);
        }

        public function testSinEspaciadoNoSeMueveNada(): void
        {
            $base     = 1_800_000_000;
            $entradas = Queue::spaced([
                ['kind' => 'schedule', 'post_id' => 1, 'time' => $base],
                ['kind' => 'schedule', 'post_id' => 2, 'time' => $base],
            ], 0);

            $this->assertSame([$base, $base], $this->horas($entradas));
            $this->assertFalse($entradas[1]['moved']);
        }

        public function testLoQueYaEstabaSeparadoNoSeToca(): void
        {
            $base     = 1_800_000_000;
            $entradas = Queue::spaced([
                ['kind' => 'schedule', 'post_id' => 1, 'time' => $base],
                ['kind' => 'schedule', 'post_id' => 2, 'time' => $base + 1800],
                ['kind' => 'retry', 'id' => 9, 'time' => $base + 7200],
            ], 1800);

            $this->assertSame([$base, $base + 1800, $base + 7200], $this->horas($entradas));
            foreach ($entradas as $entrada) {
                $this->assertFalse($entrada['moved']);
            }
        }

        public function testElRecolocadoNoPierdeNiDesordenaEnvios(): void
        {
            $base = 1_800_000_000;

            $entradas = Queue::spaced([
                ['kind' => 'schedule', 'post_id' => 3, 'time' => $base + 30],
                ['kind' => 'schedule', 'post_id' => 1, 'time' => $base],
                ['kind' => 'retry', 'id' => 7, 'time' => $base + 10],
            ], 600);

            $this->assertCount(3, $entradas, 'Ningún envío se pierde.');

            $horas = $this->horas($entradas);
            $this->assertSame([$base, $base + 600, $base + 1200], $horas, 'Quedan ordenados y separados.');
            $this->assertSame(1, $entradas[0]['post_id'], 'Sale primero el que iba antes.');
        }

        // ── El intervalo ────────────────────────────────────────────────────

        public function testElIntervaloPorDefectoEsMediaHora(): void
        {
            $this->assertSame(1800, Queue::interval());
        }

        public function testElIntervaloSeGuardaYSeLimita(): void
        {
            $this->assertSame(900, Queue::save_interval(900));
            $this->assertSame(900, Queue::interval());

            $this->assertSame(0, Queue::save_interval(-40), 'Nunca negativo: 0 significa sin espaciado.');
            $this->assertSame(86400, Queue::save_interval(999999), 'Como mucho, un día.');
        }

        // ── Publicar respetando el espaciado ────────────────────────────────

        public function testNoSePublicaNadaSiAcabaDeSalirOtraCosa(): void
        {
            Queue::save_interval(1800);
            Queue::mark_published(1_800_000_000);

            $this->assertFalse(Queue::can_publish_now(1_800_000_000 + 60));
            $this->assertTrue(Queue::can_publish_now(1_800_000_000 + 1800));
        }

        public function testSinEspaciadoSiempreSePuedePublicar(): void
        {
            Queue::save_interval(0);
            Queue::mark_published(time());

            $this->assertTrue(Queue::can_publish_now());
        }

        public function testLaPrimeraVezSePublicaSinEsperar(): void
        {
            $this->assertTrue(Queue::can_publish_now());
        }

        // ── Qué hay en la cola ──────────────────────────────────────────────

        public function testUnEnvioProgramadoSalePorCadaCuenta(): void
        {
            $this->dosCuentas();
            $base = 1_800_000_000;
            $this->postProgramado(11, $base, 'Fiesta del centro');

            $entradas = Queue::scheduled_entries($base - 60, $base + 60);

            $this->assertCount(2, $entradas, 'Dos cuentas, dos envíos.');
            $this->assertSame('Fiesta del centro', $entradas[0]['title']);
            $this->assertSame('telegram', $entradas[0]['network']);
            $this->assertNotSame($entradas[0]['account'], $entradas[1]['account'], 'Cada envío en su cuenta.');
        }

        public function testNoSePintaLaCuentaQueElEditorDesmarco(): void
        {
            $ids  = $this->dosCuentas();
            $base = 1_800_000_000;
            $this->postProgramado(12, $base, 'Solo a una cuenta', [$ids[1]]);

            $entradas = Queue::scheduled_entries($base - 60, $base + 60);

            $this->assertCount(1, $entradas);
            $this->assertSame($ids[0], $entradas[0]['account'], 'Solo va la cuenta que el editor dejó marcada.');
        }

        public function testNoSePintaUnaCuentaSinCredenciales(): void
        {
            Profile_Store::create('telegram', 'Telegram — a medias');
            $base = 1_800_000_000;
            $this->postProgramado(13, $base, 'Sin token');

            $this->assertSame([], Queue::scheduled_entries($base - 60, $base + 60));
        }

        public function testLosReintentosYSusFallosEntranEnLaCola(): void
        {
            $ids = $this->dosCuentas();

            $GLOBALS['_cp_test_db']['results'] = [
                (object) [
                    'id' => 5, 'post_id' => 20, 'channel' => $ids[0], 'payload' => '',
                    'error_text' => 'HTTP 500', 'attempts' => 2, 'status' => 'failed',
                    'last_attempt' => '2026-01-01 10:00:00', 'next_attempt' => '2026-01-01 11:00:00',
                    'created_at' => '2026-01-01 09:00:00',
                ],
            ];

            $entradas = Queue::retry_entries();

            $this->assertCount(1, $entradas);
            $this->assertSame('retry', $entradas[0]['kind']);
            $this->assertSame(5, $entradas[0]['id']);
            $this->assertSame('failed', $entradas[0]['status']);
            $this->assertSame('HTTP 500', $entradas[0]['error']);
            $this->assertSame('Telegram — Centro Social', $entradas[0]['account_name'], 'Dice la cuenta, no solo el id.');
            $this->assertGreaterThan(0, $entradas[0]['time']);
        }

        public function testElHistorialSeLeeDelMasNuevoAlMasViejo(): void
        {
            update_option('convoca_publisher_publish_log', [
                ['title' => 'Antigua', 'channel' => 'Telegram', 'success' => true, 'time' => '2026-01-01 10:00:00', 'response' => 'ok'],
                ['title' => 'Reciente', 'channel' => 'Mastodon', 'success' => false, 'time' => '2026-01-02 10:00:00', 'response' => 'error'],
            ]);

            $entradas = Queue::sent_entries();

            $this->assertSame('Reciente', $entradas[0]['title']);
            $this->assertFalse($entradas[0]['success']);
            $this->assertSame('Antigua', $entradas[1]['title']);
        }

        // ── Recolocar, reprogramar y quitar de la cola ──────────────────────

        public function testRecolocarGuardaLaHoraNueva(): void
        {
            Profile_Store::create('telegram', 'Telegram', [
                'convoca_publisher_telegram_token'   => 'T',
                'convoca_publisher_telegram_chat_id' => '-100',
            ]);

            $base = time() + 3600;
            $this->postProgramado(31, $base, 'Primera');
            $this->postProgramado(32, $base, 'Segunda');

            $movidos = Queue::apply_spacing();

            $this->assertSame(1, $movidos, 'Solo se recoloca la segunda.');
            $this->assertSame($base, (int) get_post_meta(31, Queue::SCHEDULE_META, true));
            $this->assertSame($base + Queue::interval(), (int) get_post_meta(32, Queue::SCHEDULE_META, true));
        }

        public function testSinEspaciadoNoSeRecolocaNada(): void
        {
            Queue::save_interval(0);
            Profile_Store::create('telegram', 'Telegram', [
                'convoca_publisher_telegram_token'   => 'T',
                'convoca_publisher_telegram_chat_id' => '-100',
            ]);
            $base = time() + 3600;
            $this->postProgramado(41, $base);
            $this->postProgramado(42, $base);

            $this->assertSame(0, Queue::apply_spacing());
            $this->assertSame($base, (int) get_post_meta(42, Queue::SCHEDULE_META, true));
        }

        public function testReprogramarYQuitarDeLaCola(): void
        {
            $this->postProgramado(51, time() + 3600, 'Se mueve');

            $this->assertTrue(Queue::reschedule_schedule(51, time() + 7200));
            $this->assertSame(time() + 7200, (int) get_post_meta(51, Queue::SCHEDULE_META, true) + (time() + 7200 - (time() + 7200)));
            $this->assertGreaterThan(time() + 7000, (int) get_post_meta(51, Queue::SCHEDULE_META, true));

            $this->assertFalse(Queue::reschedule_schedule(51, 0), 'Una hora sin sentido no se guarda.');

            $this->assertTrue(Queue::cancel_schedule(51));
            $this->assertSame('', (string) get_post_meta(51, Queue::SCHEDULE_META, true), 'Quitar de la cola no borra la entrada: solo su envío programado.');
        }

        public function testUnReintentoSePuedeReprogramarYQuitar(): void
        {
            $this->assertTrue(Queue::reschedule_retry(9, time() + 600));
            $this->assertTrue(Queue::cancel_retry(9));
        }

        /**
         * La regla de a quién va la entrada la aplica también el publicador: sin esta
         * prueba, volver a decidirlo por su cuenta (y mandar a una cuenta desmarcada) no
         * rompía nada.
         */
        public function testElPublicadorSoloMandaALasCuentasQueTocan(): void
        {
            $ids = $this->dosCuentas();
            $GLOBALS['_cp_test_postmeta'][77]['_convoca_publisher_disabled_channels'] = [$ids[1]];
            $GLOBALS['_cp_test_envios'] = [];

            $canales = [];

            foreach (Plugin::accounts() as $id => $cuenta) {
                $canales[$id] = new class ($id) implements \ConvocaPublisher\Channels\ChannelInterface {
                    private string $id;

                    public function __construct(string $id)
                    {
                        $this->id = $id;
                    }

                    public function get_id(): string
                    {
                        return $this->id;
                    }

                    public function get_name(): string
                    {
                        return $this->id;
                    }

                    public function is_available(): bool
                    {
                        return true;
                    }

                    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
                    {
                        $GLOBALS['_cp_test_envios'][] = $this->id;

                        return ['success' => true, 'post_id' => 'x'];
                    }

                    public function get_settings_fields(): array
                    {
                        return [];
                    }

                    public function validate_settings(array $settings): array
                    {
                        return [];
                    }

                    public function verify_connection(): array
                    {
                        return ['success' => true];
                    }
                };
            }

            (new Publisher($canales))->publish_post(77, true, true);

            $this->assertSame([$ids[0]], $GLOBALS['_cp_test_envios'], 'Solo la cuenta que sigue marcada recibe la entrada.');
        }
    }
}

namespace {
}
