<?php

/**
 * Vista del historial: filtros, recuentos y qué se puede reintentar.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Admin\Log_View;
use PHPUnit\Framework\TestCase;

final class LogViewTest extends TestCase
{
    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function fila(string $channel, bool $ok, int $post_id = 10, array $extra = []): array
    {
        return array_merge([
            'success' => $ok,
            'channel' => $channel,
            'post_id' => $post_id,
            'title'   => 'Una entrada ' . $post_id,
            'time'    => '2026-09-11 10:00:00',
        ], $extra);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historial(): array
    {
        return [
            $this->fila('telegram', true, 10),
            $this->fila('telegram', false, 11),
            $this->fila('telegram-apuntes', false, 11),
            $this->fila('linkedin', true, 12),
            $this->fila('VALIDACIÓN', false, 0),
            $this->fila('facebook', false, 0),
        ];
    }

    public function testSinFiltrosSaleTodo(): void
    {
        $this->assertCount(6, Log_View::filter($this->historial(), []));
    }

    public function testFiltraPorEstado(): void
    {
        $ok = Log_View::filter($this->historial(), ['status' => 'ok']);
        $this->assertCount(2, $ok);
        foreach ($ok as $fila) {
            $this->assertTrue($fila['success']);
        }

        $fail = Log_View::filter($this->historial(), ['status' => 'fail']);
        $this->assertCount(4, $fail);
    }

    public function testFiltraPorCuentaExacta(): void
    {
        $filas = Log_View::filter($this->historial(), ['account' => 'telegram']);
        $this->assertCount(2, $filas);

        foreach ($filas as $fila) {
            $this->assertSame('telegram', $fila['channel']);
        }
    }

    public function testFiltraPorRedConElMapaDeCuentas(): void
    {
        $mapa = ['telegram-apuntes' => 'telegram'];
        $filas = Log_View::filter($this->historial(), ['network' => 'telegram', 'networks' => $mapa]);

        $this->assertCount(3, $filas);
    }

    public function testUnaCuentaBorradaSigueApareciendo(): void
    {
        // Sin mapa no hay red conocida: la fila se agrupa por su propio id y no se pierde.
        $this->assertSame('facebook', Log_View::network_of('facebook', []));
        $this->assertCount(1, Log_View::filter($this->historial(), ['network' => 'facebook']));
    }

    public function testLosFiltrosSeCombinan(): void
    {
        $mapa = ['telegram-apuntes' => 'telegram'];
        $filas = Log_View::filter($this->historial(), ['network' => 'telegram', 'status' => 'fail', 'networks' => $mapa]);

        $this->assertCount(2, $filas);
        $this->assertSame(['telegram', 'telegram-apuntes'], array_column($filas, 'channel'));
    }

    public function testUnFiltroSinCoincidenciasDevuelveVacio(): void
    {
        $this->assertSame([], Log_View::filter($this->historial(), ['network' => 'mastodon']));
        $this->assertSame([], Log_View::filter($this->historial(), ['account' => 'no-existe']));
    }

    public function testLasFilasRarasNoRompen(): void
    {
        $entrada = [null, 'texto', [], $this->fila('telegram', true)];

        $this->assertCount(1, Log_View::filter($entrada, []), 'sin canal no es una fila del historial');
        $this->assertSame(1, Log_View::facets($entrada)['total']);
    }

    public function testCuentaLosEstadosYLoQueHay(): void
    {
        $datos = Log_View::facets($this->historial(), ['telegram-apuntes' => 'telegram']);

        $this->assertSame(6, $datos['total']);
        $this->assertSame(['ok' => 2, 'fail' => 4], $datos['statuses']);
        $this->assertSame(['facebook', 'linkedin', 'telegram'], $datos['networks']);
        $this->assertArrayHasKey('telegram-apuntes', $datos['accounts']);
    }

    public function testSoloSeReintentaUnEnvioFallidoConEntrada(): void
    {
        $this->assertTrue(Log_View::retryable($this->fila('telegram', false, 11)));
        $this->assertFalse(Log_View::retryable($this->fila('telegram', true, 11)), 'lo que ya salió no se reintenta');
        $this->assertFalse(Log_View::retryable($this->fila('telegram', false, 0)), 'sin entrada no hay nada que reenviar');
        $this->assertFalse(Log_View::retryable($this->fila('', false, 11)), 'sin cuenta no se sabe a dónde');
        $this->assertFalse(Log_View::retryable($this->fila('VALIDACIÓN', false, 11)), 'los avisos del plugin no son envíos');
        $this->assertFalse(Log_View::retryable($this->fila('SISTEMA', false, 11)));
    }

    public function testElNombreDeLaCuentaLlevaSuRed(): void
    {
        $mapa  = ['telegram-apuntes' => 'telegram'];
        $nombres = ['telegram-apuntes' => 'Apuntes'];

        $this->assertSame('telegram · Apuntes', Log_View::label('telegram-apuntes', $mapa, $nombres));
        $this->assertSame('telegram', Log_View::label('telegram', $mapa), 'si el nombre es la red, no se repite');
        $this->assertSame('facebook', Log_View::label('facebook', $mapa), 'sin red conocida, el id tal cual');
        $this->assertSame('', Log_View::label(''));
    }
}
