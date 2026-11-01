<?php

/**
 * Los manejadores de la cola: la puerta, antes que el efecto.
 *
 * `handle_queue_cancel`, `handle_queue_reschedule`, `handle_queue_spacing` y
 * `handle_queue_send_stuck` escriben en la base de datos. Hasta ahora ninguna prueba los llamaba:
 * la lógica de `Queue` sí está cubierta, pero **quién puede invocarla** no se estaba midiendo.
 *
 * Aquí se fija lo que sí es medible hoy: sin permiso, `wp_die` corta y **no se toca nada**.
 *
 * Lo que no se puede probar todavía, y está anotado en la spec: el camino feliz de estos
 * manejadores termina en `exit`, así que ejecutarlo dentro de PHPUnit mata la corrida. Y el doble
 * de `check_admin_referer` devuelve siempre 1, así que una prueba de nonce sería una prueba que no
 * puede fallar: se escribiría verde y no protegería nada.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Admin;
    use ConvocaPublisher\Queue;
    use PHPUnit\Framework\TestCase;

    /**
     * La puerta de los manejadores de la cola.
     */
    final class AdminQueueHandlersTest extends TestCase
    {
        /**
         * Prepara el caso.
         */
        protected function setUp(): void
        {
            \cp_test_reset();

            parent::setUp();

            $GLOBALS['_cp_test_options']   = [];
            $GLOBALS['_cp_test_postmeta']  = [];
            $GLOBALS['_cp_test_posts']     = [];
            $GLOBALS['_cp_test_db']        = ['rows' => [], 'inserts' => [], 'results' => []];
            $GLOBALS['_cp_test_wp_die']    = null;
            $_POST                         = [];
        }

        /**
         * Deja un envío programado y devuelve su identificador.
         *
         * @param int $id Identificador de la entrada.
         * @param int $cuando Sello de tiempo del envío.
         */
        private function programar(int $id, int $cuando): int
        {
            $GLOBALS['_cp_test_posts'][]                            = $id;
            $GLOBALS['_cp_test_titles'][$id]                        = 'Entrada ' . $id;
            $GLOBALS['_cp_test_postmeta'][$id][Queue::SCHEDULE_META] = $cuando;

            return $id;
        }

        /**
         * El sello programado de una entrada, tal como está en la base.
         *
         * Se mira el metadato y no `scheduled_entries()`: esa función solo devuelve filas si hay
         * cuentas configuradas, y con cero cuentas devuelve vacío aunque el envío esté ahí. Una
         * aserción contra ella pasaría siempre y no mediría lo que dice medir.
         *
         * @param int $id Identificador de la entrada.
         */
        private function programado(int $id): int
        {
            return (int) get_post_meta($id, Queue::SCHEDULE_META, true);
        }

        /**
         * Sin permiso, quitar de la cola no quita nada.
         */
        public function test_sin_permiso_no_se_quita_de_la_cola(): void
        {
            $base = 1_800_000_000;
            $this->programar(51, $base);
            $GLOBALS['_cp_test_can_manage'] = false;
            $_POST['convoca_publisher_envio'] = '51';

            try {
                Admin::handle_queue_cancel();
                $this->fail('sin permiso tenía que cortar con wp_die');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('wp_die', $e->getMessage());
            }

            $this->assertSame($base, $this->programado(51), 'el envío sigue programado');
        }

        /**
         * Sin permiso, recolocar no mueve nada.
         */
        public function test_sin_permiso_no_se_recoloca(): void
        {
            $base = 1_800_050_000;
            $this->programar(61, $base);
            $this->programar(62, $base + 30);
            $GLOBALS['_cp_test_can_manage'] = false;

            $antes = array($this->programado(61), $this->programado(62));

            try {
                Admin::handle_queue_spacing();
                $this->fail('sin permiso tenía que cortar con wp_die');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('wp_die', $e->getMessage());
            }

            $this->assertSame($antes, array($this->programado(61), $this->programado(62)), 'nada se ha recolocado');
        }

        /**
         * Sin permiso, ni cancelar ni forzar un atascado tocan la cola.
         */
        public function test_sin_permiso_los_otros_manejadores_tampoco(): void
        {
            $base = 1_800_090_000;
            $this->programar(71, $base);
            $GLOBALS['_cp_test_postmeta'][71]['_convoca_publisher_stuck'] = 1;
            $GLOBALS['_cp_test_can_manage'] = false;

            foreach (['handle_queue_reschedule', 'handle_queue_send_stuck'] as $manejador) {
                try {
                    Admin::$manejador();
                    $this->fail($manejador . ' tenía que cortar con wp_die');
                } catch (\RuntimeException $e) {
                    $this->assertStringContainsString('wp_die', $e->getMessage());
                }
            }

            $this->assertSame($base, $this->programado(71), 'el envío sigue donde estaba');
        }
    }
}
