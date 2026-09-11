<?php

/**
 * El historial: la paginación y el rastro de las pruebas.
 *
 * Dos cosas que pidió JC sobre la pantalla real:
 *
 * - Las pruebas de publicación **mandan un mensaje de verdad**, así que tienen que dejar rastro:
 *   «¿salió?» tiene que poder responderse mirando el historial, y antes no se podía.
 * - El historial guarda hasta 200 filas y se volcaban todas de una vez: ahora se pagina de 25 en
 *   25, enseñando lo último primero.
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Admin\Log_View;
use ConvocaPublisher\Publisher;
use ConvocaPublisher\Tests\Support\FakeChannel;
use PHPUnit\Framework\TestCase;

final class LogHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();

        $GLOBALS['_cp_test_options']  = [];
        $GLOBALS['_cp_test_titles'][7] = 'Taller de huerto';
    }

    /** Una lista de filas, de la más vieja a la más nueva, como se guarda. */
    private function filas(int $cuantas): array
    {
        $filas = [];
        for ($i = 1; $i <= $cuantas; ++$i) {
            $filas[] = ['channel' => 'telegram', 'success' => true, 'title' => 'Entrada ' . $i, 'time' => '2026-09-11 10:00:0' . ($i % 10)];
        }

        return $filas;
    }

    public function testSePaginaDeVeinticincoEnVeinticincoYLoUltimoPrimero(): void
    {
        $pagina = Log_View::page($this->filas(30), 1, 25);

        $this->assertCount(25, $pagina['entries'], 'La primera página trae 25 filas.');
        $this->assertSame(2, $pagina['pages'], 'Y hay dos páginas.');
        $this->assertSame(30, $pagina['total'], 'Con 30 en total.');
        $this->assertSame('Entrada 30', $pagina['entries'][0]['title'], 'Lo último que pasó sale primero.');
        $this->assertSame('Entrada 6', $pagina['entries'][24]['title'], 'Y la página acaba en la fila 6.');

        $segunda = Log_View::page($this->filas(30), 2, 25);
        $this->assertCount(5, $segunda['entries'], 'La segunda trae las 5 que quedan.');
        $this->assertSame('Entrada 5', $segunda['entries'][0]['title'], 'Y sigue el orden hacia atrás.');
    }

    public function testUnaPaginaFueraDeRangoNoRompe(): void
    {
        $this->assertSame(2, Log_View::page($this->filas(30), 99, 25)['page'], 'Si se pide una página que no existe, se da la última.');
        $this->assertSame(1, Log_View::page($this->filas(30), -3, 25)['page'], 'Y si se pide una de menos, la primera.');
        $this->assertSame(1, Log_View::page([], 1, 25)['pages'], 'Sin historial, una página vacía: nunca cero.');
    }

    public function testUnaPruebaDePublicacionDejaRastroMarcadoComoPrueba(): void
    {
        $canal = new FakeChannel('telegram', 'telegram', 'Telegram');
        Publisher::init(['telegram' => $canal]);

        Publisher::instance()->publish_test(7, 'telegram');

        $log = get_option('convoca_publisher_publish_log', []);
        $this->assertCount(1, $log, 'La prueba queda en el historial: si no, no se puede saber si salió.');
        $this->assertTrue($log[0]['test'] ?? false, 'Y va marcada como prueba, para no confundirla con una publicación.');
        $this->assertSame('telegram', $log[0]['channel'], 'Con el id de la cuenta, que es lo que el historial espera (no el nombre).');
        $this->assertTrue($log[0]['success'], 'Y con el resultado.');
    }
}
