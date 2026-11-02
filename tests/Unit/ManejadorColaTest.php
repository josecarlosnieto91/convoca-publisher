<?php

/**
 * Los manejadores de la cola, ya sin `exit`: se prueba el efecto, no solo la puerta.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Manejador_Cola;
    use ConvocaPublisher\Queue;
    use PHPUnit\Framework\TestCase;

    /**
     * Efecto de cada manejador sobre la cola.
     */
    final class ManejadorColaTest extends TestCase
    {
        /**
         * Prepara la cola vacía.
         */
        protected function setUp(): void
        {
            \cp_test_reset();

            parent::setUp();

            $GLOBALS['_cp_test_options']  = [];
            $GLOBALS['_cp_test_postmeta'] = [];
            $GLOBALS['_cp_test_posts']    = [];
            $GLOBALS['_cp_test_titles']   = [];
            $GLOBALS['_cp_test_db']       = ['rows' => [], 'inserts' => [], 'results' => []];
            $_POST                        = [];
        }

        /**
         * Deja un envío programado.
         *
         * @param int $id     Identificador de la entrada.
         * @param int $cuando Sello de tiempo del envío.
         */
        private function programar(int $id, int $cuando): void
        {
            $GLOBALS['_cp_test_posts'][]                            = $id;
            $GLOBALS['_cp_test_titles'][$id]                        = 'Entrada ' . $id;
            $GLOBALS['_cp_test_postmeta'][$id][Queue::SCHEDULE_META] = $cuando;
        }

        /**
         * El sello que tiene ahora una entrada.
         *
         * @param int $id Identificador de la entrada.
         */
        private function sello(int $id): int
        {
            return (int) get_post_meta($id, Queue::SCHEDULE_META, true);
        }

        /**
         * Quitar un envío lo quita y lo dice.
         */
        public function test_cancelar_quita_el_envio(): void
        {
            $this->programar(51, 1_800_000_000);

            $resultado = Manejador_Cola::cancelar('schedule:51');

            $this->assertTrue($resultado['ok']);
            $this->assertSame('Envío quitado de la cola.', $resultado['aviso']);
            $this->assertSame(0, $this->sello(51), 'el envío ya no está programado');
        }

        /**
         * Quitar algo que no está no se puede, y no se miente diciendo que sí.
         */
        public function test_cancelar_lo_que_no_existe_lo_dice(): void
        {
            $resultado = Manejador_Cola::cancelar('schedule:999');

            $this->assertFalse($resultado['ok']);
            $this->assertStringContainsString('ya no estaba en la cola', $resultado['aviso']);
        }

        /**
         * Una referencia sin identificador no toca nada.
         */
        public function test_cancelar_una_referencia_vacia_no_toca_nada(): void
        {
            $this->programar(52, 1_800_000_100);

            $resultado = Manejador_Cola::cancelar('schedule:');

            $this->assertFalse($resultado['ok']);
            $this->assertSame(1_800_000_100, $this->sello(52));
        }

        /**
         * Reprogramar mueve el envío a la hora pedida, en la zona del sitio.
         */
        public function test_reprogramar_mueve_el_envio(): void
        {
            $this->programar(61, 1_800_050_000);
            $esperado = Manejador_Cola::parse_when('', '2026-12-01T10:00');

            $resultado = Manejador_Cola::reprogramar('schedule:61', '', '2026-12-01T10:00');

            $this->assertTrue($resultado['ok']);
            $this->assertSame('Envío reprogramado.', $resultado['aviso']);
            $this->assertSame($esperado, $this->sello(61));
        }

        /**
         * Una fecha que no se entiende no mueve nada y no inventa un aviso.
         */
        public function test_reprogramar_con_fecha_basura_no_toca_ni_avisa(): void
        {
            $this->programar(62, 1_800_050_500);

            $resultado = Manejador_Cola::reprogramar('schedule:62', '', 'mañana por la tarde');

            $this->assertFalse($resultado['ok']);
            $this->assertSame('', $resultado['aviso'], 'antes tampoco decía nada');
            $this->assertSame(1_800_050_500, $this->sello(62), 'el envío sigue donde estaba');
        }

        /**
         * Recolocar informa de cuántos ha movido, aunque sean cero.
         */
        public function test_recolocar_informa_del_recuento(): void
        {
            $resultado = Manejador_Cola::recolocar();

            $this->assertIsBool($resultado['ok']);
            $this->assertMatchesRegularExpression('/^\d+ envíos? recolocados?\.$/', (string) $resultado['aviso']);
        }

        /**
         * El formulario acepta las dos formas de decir cuándo.
         */
        public function test_las_dos_formas_de_decir_cuando(): void
        {
            $porDia = Manejador_Cola::parse_when('2026-12-24', '');
            $porHora = Manejador_Cola::parse_when('', '2026-12-24T18:30');

            $this->assertGreaterThan(0, $porDia);
            $this->assertGreaterThan(0, $porHora);
            $this->assertNotSame($porDia, $porHora);
            $this->assertSame(0, Manejador_Cola::parse_when('', ''));
        }
    }
}
