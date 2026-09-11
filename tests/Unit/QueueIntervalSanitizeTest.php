<?php

/**
 * El saneador del intervalo de la cola.
 *
 * Este fichero existe por un 500 de verdad: al guardar una plantilla, el guardado moría con la
 * pila llena de marcos repetidos.
 *
 * El saneador del intervalo llamaba a `Queue::save_interval()`, que guarda con `update_option()`.
 * Al guardar, WordPress pasa otra vez por `sanitize_option` y vuelve a llamar al saneador: se
 * llamaba a sí mismo sin fin. Y saltaba **guardando una plantilla** porque el grupo de ajustes se
 * guarda entero desde esa pestaña, aunque el intervalo ni se toque.
 *
 * Lo que se fija aquí es la regla que se rompió: **un saneador no escribe la opción que sanea**.
 * Devuelve el valor y ya; de guardarlo se encarga WordPress.
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Admin;
use ConvocaPublisher\Queue;
use PHPUnit\Framework\TestCase;

final class QueueIntervalSanitizeTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();

        $GLOBALS['_cp_test_option_writes'] = [];
    }

    public function testElSaneadorDelIntervaloNoEscribeLaOpcion(): void
    {
        $devuelto = Admin::sanitize_queue_interval(90);

        $this->assertSame(90, $devuelto, 'Devuelve el valor, que es su trabajo.');
        $this->assertSame(
            [],
            $GLOBALS['_cp_test_option_writes'],
            'Y no escribe: si escribe, WordPress vuelve a pasar por el saneador al guardar y se llama a sí mismo sin fin.'
        );
    }

    public function testRecortaIgualQueLaEscrituraDirecta(): void
    {
        foreach ([0, 90, 3600, PHP_INT_MAX] as $valor) {
            $this->assertSame(
                Queue::clamp_interval($valor),
                Admin::sanitize_queue_interval($valor),
                'El saneador y la escritura programática tienen que recortar igual: ' . $valor
            );
        }
    }

    public function testElTopeEsUnDiaYElSueloCero(): void
    {
        $this->assertSame(DAY_IN_SECONDS, Queue::clamp_interval(PHP_INT_MAX), 'Ni un intervalo eterno.');
        $this->assertSame(0, Queue::clamp_interval(-5), 'Ni negativo.');
    }
}
