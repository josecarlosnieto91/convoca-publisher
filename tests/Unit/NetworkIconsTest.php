<?php

/**
 * Los iconos de las redes.
 *
 * Lo que se fija aquí no es que el dibujo sea bonito (eso se mira con los ojos), sino que:
 *
 * - **Ninguna red se quede sin icono.** Si mañana entra una red nueva al plugin y nadie le hace
 *   el dibujo, esta prueba avisa en vez de dejar un hueco en la pantalla.
 * - **Nunca devuelva vacío**, ni siquiera para una red desconocida: un hueco se lee como un
 *   fallo del plugin.
 * - **Herede el color del texto** (`currentColor`), que es lo que hace que funcione igual en
 *   modo claro y en oscuro sin una regla más.
 * - **No hable a los lectores de pantalla**: el nombre de la red va al lado, así que el icono
 *   es decorativo y repetirlo sería ruido.
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Icons;
use ConvocaPublisher\Plugin;
use PHPUnit\Framework\TestCase;

final class NetworkIconsTest extends TestCase
{
    public function testNingunaRedSeQuedaSinDibujoPropio(): void
    {
        $redes   = array_keys(Plugin::networks());
        $sinIcono = array_values(array_diff($redes, Icons::networks()));

        $this->assertNotEmpty($redes, 'El plugin trae redes.');
        $this->assertSame([], $sinIcono, 'Todas tienen dibujo propio. Les falta: ' . implode(', ', $sinIcono));
    }

    public function testCadaIconoEsUnSvgQueHeredaElColorDelTexto(): void
    {
        foreach (array_keys(Plugin::networks()) as $red) {
            $svg = Icons::svg((string) $red);

            $this->assertStringContainsString('<svg', $svg, 'La red ' . $red . ' trae SVG.');
            $this->assertStringContainsString('currentColor', $svg, 'Y hereda el color: ' . $red);
            $this->assertStringContainsString('viewBox="0 0 24 24"', $svg, 'Con el mismo lienzo: ' . $red);
        }
    }

    public function testUnaRedDesconocidaRecibeElGenericoYNoUnHueco(): void
    {
        $svg = Icons::svg('red-que-no-existe-todavia');

        $this->assertStringContainsString('<svg', $svg, 'Nunca devuelve vacío.');
        $this->assertStringContainsString('currentColor', $svg, 'Y el genérico también hereda el color.');
    }

    public function testVaOcultoALosLectoresDePantalla(): void
    {
        $svg = Icons::svg('telegram');

        $this->assertStringContainsString('aria-hidden="true"', $svg, 'Es decorativo: el nombre va al lado.');
        $this->assertStringContainsString('focusable="false"', $svg, 'Y no se cuela en el orden del tabulador.');
    }
}
