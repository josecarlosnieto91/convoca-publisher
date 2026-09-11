<?php

/**
 * Reglas por red: lo que cabe, lo que solo conviene y cómo se recorta sin destrozar el enlace.
 *
 * Lo que se fija aquí es que el contador **no mienta**: X cuenta cualquier enlace como 23
 * caracteres, y medir el texto tal cual daría un número que no es el que la red usa.
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Platform_Rules;
    use PHPUnit\Framework\TestCase;

    final class PlatformRulesTest extends TestCase
    {
        public function testCadaRedTieneSuTope(): void
        {
            $this->assertSame(280, Platform_Rules::limit('twitter'));
            $this->assertSame(500, Platform_Rules::limit('mastodon'));
            $this->assertSame(4096, Platform_Rules::limit('telegram'));
            $this->assertSame(3000, Platform_Rules::limit('linkedin'));
        }

        public function testTodosLosCanalesDelPluginTienenSusReglas(): void
        {
            $canales = array_keys(\ConvocaPublisher\Plugin::networks());
            $sin_reglas = array_diff($canales, Platform_Rules::networks());

            $this->assertSame(
                [],
                array_values($sin_reglas),
                'Un canal sin reglas propias cuenta con el tope genérico: nadie se enteraría de que se queda sin revisar.'
            );
        }

        public function testUnaRedDesconocidaNoSeQuedaSinReglas(): void
        {
            $reglas = Platform_Rules::rules('bluesky');

            $this->assertGreaterThan(0, $reglas['chars'], 'Una red nueva no puede quedar sin tope.');
            $this->assertSame(0, $reglas['hashtags'], 'Y sin reglas recomendadas inventadas.');
        }

        public function testElContadorDeXCuentaElEnlaceComoLoCuentaX(): void
        {
            $enlace = 'https://lugg.biodevas.org/actividades/taller-de-huerto-y-compostaje-comunitario/';
            $texto  = 'Taller de huerto ' . $enlace;

            // 16 letras + espacio + los 23 que cuenta X, no los 80 y pico que mide el enlace.
            $this->assertSame(40, Platform_Rules::count('twitter', $texto));
            $this->assertSame(mb_strlen($texto), Platform_Rules::count('mastodon', $texto), 'Las demás redes cuentan lo que mide.');
        }

        public function testUnEnlaceLargoCabeEnXGraciasAlPesoFijo(): void
        {
            $mensaje = str_repeat('a', 250) . ' https://lugg.biodevas.org/una/ruta/larguisima/que/ocupa/mucho/espacio/';

            $this->assertTrue(Platform_Rules::check('twitter', $mensaje)['fit'], 'Con el peso real del enlace, cabe.');
            $this->assertGreaterThan(280, mb_strlen($mensaje), 'Aunque medido a pelo no quepa.');
        }

        public function testPasarseDeCaracteresEsUnProblema(): void
        {
            $resultado = Platform_Rules::check('twitter', str_repeat('a', 300));

            $this->assertFalse($resultado['ok']);
            $this->assertNotEmpty($resultado['problems']);
            $this->assertSame(300, $resultado['chars']);
            $this->assertSame(280, $resultado['limit']);
        }

        public function testUnMensajeQueCabeNoTraeAvisos(): void
        {
            $resultado = Platform_Rules::check('twitter', 'Asamblea de socios el jueves https://lugg.biodevas.org/ #asamblea');

            $this->assertTrue($resultado['ok']);
            $this->assertSame([], $resultado['problems']);
            $this->assertSame([], $resultado['warnings']);
            $this->assertSame($resultado['message'], 'Asamblea de socios el jueves https://lugg.biodevas.org/ #asamblea', 'Lo que cabe no se toca.');
        }

        public function testDemasiadasEtiquetasAvisanPeroNoImpidenEnviar(): void
        {
            $resultado = Platform_Rules::check('twitter', '#uno #dos #tres #cuatro #cinco');

            $this->assertTrue($resultado['ok'], 'Las etiquetas son recomendación, no tope duro.');
            $this->assertNotEmpty($resultado['warnings']);
            $this->assertSame([], $resultado['problems']);
        }

        public function testUnMensajeQueNoCabeSeRecortaYConservaElEnlace(): void
        {
            $enlace  = 'https://lugg.biodevas.org/actividades/';
            $mensaje = str_repeat('palabra ', 60) . $enlace;

            // En X, que además cuenta el enlace con su peso: el recorte tiene que valer ahí.
            $recortado = Platform_Rules::trim('twitter', $mensaje);

            $this->assertLessThanOrEqual(280, Platform_Rules::count('twitter', $recortado));
            $this->assertStringContainsString($enlace, $recortado, 'El enlace no se rompe: es lo que se quiere que llegue.');
            $this->assertStringEndsWith($enlace, $recortado, 'Y queda al final, una sola vez.');
            $this->assertSame(1, substr_count($recortado, $enlace));
        }

        public function testRecortarNoRepiteElEnlaceNiDejaBasura(): void
        {
            $enlace  = 'https://getconvoca.app';
            $mensaje = str_repeat('texto largo ', 30) . ' ' . $enlace . ' y más texto detrás';

            $recortado = Platform_Rules::trim('twitter', $mensaje);

            $this->assertSame(1, substr_count($recortado, $enlace));
            $this->assertStringNotContainsString('  ', $recortado, 'Sin dobles espacios al quitar el enlace de su sitio.');
            $this->assertStringNotContainsString(' y más texto detrás', $recortado, 'Lo que sobra se va.');
        }

        public function testUnMensajeSinEnlaceTambienSeRecorta(): void
        {
            $recortado = Platform_Rules::trim('twitter', str_repeat('x', 400));

            $this->assertLessThanOrEqual(280, mb_strlen($recortado));
            $this->assertStringEndsWith('…', $recortado);
        }

        public function testElRecuentoDeEtiquetasNoSeTragaLasAlmohadillasDeUnaUrl(): void
        {
            $this->assertSame(2, Platform_Rules::hashtag_count('Mira https://ejemplo.org/pagina#seccion con #huerto y #compost'));
        }

        public function testLasUrlsSeExtraenSinLaPuntuacionDeAlLado(): void
        {
            $urls = Platform_Rules::urls('Ver https://lugg.biodevas.org/actividades/, y también https://getconvoca.app.');

            $this->assertCount(2, $urls);
            $this->assertSame('https://lugg.biodevas.org/actividades/', $urls[0]);
            $this->assertSame('https://getconvoca.app', $urls[1]);
        }
    }
}
