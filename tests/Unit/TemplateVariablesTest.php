<?php

/**
 * Las variables de plantilla, sacadas de comparar con otros plugins.
 *
 * Tres cosas que hacen ellos y aquí faltaban, y una trampa que traía la primera:
 *
 * - Sustituyen sin distinguir mayúsculas (SNAP usa `str_ireplace`): escribir `{Title}` tiene
 *   que dar el título, no el literal.
 * - Tienen las etiquetas como hashtags y las **categorías** como hashtags (`%HTAGS%` y
 *   `%HCATS%`): aquí solo estaban las etiquetas.
 * - Tienen la **entradilla** (`%ANNOUNCE%`): el texto anterior a `<!--more-->`.
 *
 * La trampa: `{categorias}` es el prefijo de `{categorias_hashtags}`, así que si se sustituye
 * antes la corta, el mensaje sale con un `_hashtags}` suelto.
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Publisher;
use PHPUnit\Framework\TestCase;

final class TemplateVariablesTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();

        $GLOBALS['_cp_test_categories'] = [];
        $GLOBALS['_cp_test_tags']       = [];

        Publisher::init([]);
    }

    private function entrada(string $contenido = 'Texto de la entrada.'): \WP_Post
    {
        $post               = new \WP_Post((object) []);
        $post->ID           = 7;
        $post->post_title   = 'Taller de huerto';
        $post->post_content = $contenido;
        $post->post_author  = 3;

        return $post;
    }

    private function sustituir(string $plantilla, string $contenido = 'Texto de la entrada.'): string
    {
        return Publisher::instance()->render_template($this->entrada($contenido), $plantilla);
    }

    public function testElTituloSeSustituyeAunqueSeEscribaEnMayusculas(): void
    {
        $mensaje = $this->sustituir('{Title} — {URL}');

        $this->assertStringContainsString('Taller de huerto', $mensaje, 'El título sale.');
        $this->assertStringNotContainsString('{Title}', $mensaje, 'Y no queda el literal en el mensaje.');
        $this->assertStringNotContainsString('{URL}', $mensaje, 'Ni el de la otra, tampoco en mayúsculas.');
    }

    public function testLasCategoriasComoHashtagsNoSeComenElPrefijoDeLaVariableCorta(): void
    {
        $GLOBALS['_cp_test_categories'][7] = ['Taller de huerto', 'Huerto urbano'];

        $conHashtags = $this->sustituir('{categorias_hashtags}');
        $conNombres  = $this->sustituir('{categorias}');

        $this->assertStringContainsString('#tallerdehuerto', $conHashtags, 'La categoría sale como hashtag, en minúsculas como el resto.');
        $this->assertStringNotContainsString('_hashtags}', $conHashtags, 'Sin restos: la variable corta no puede sustituirse antes.');
        $this->assertStringContainsString('#huertourbano', $conHashtags, 'Y la segunda igual: una sola palabra, sin espacios.');
        $this->assertStringContainsString('Taller de huerto', $conNombres, 'Y los nombres siguen saliendo como nombres.');
    }

    public function testLaEntradillaEsElTextoAnteriorAlSeguirLeyendo(): void
    {
        $mensaje = $this->sustituir('{entradilla}', 'Esto es la entradilla.<!--more-->Y esto el resto.');

        $this->assertStringContainsString('Esto es la entradilla.', $mensaje, 'Se queda con lo de antes.');
        $this->assertStringNotContainsString('Y esto el resto', $mensaje, 'Y el resto no se publica.');
    }

    public function testSinSeguirLeyendoLaEntradillaEsElExtracto(): void
    {
        $mensaje = $this->sustituir('{entradilla}', 'Una entrada que no lleva corte.');

        $this->assertStringContainsString('Test excerpt', $mensaje, 'Si la entrada no lleva corte, vale el extracto.');
    }

    public function testLaListaDeVariablesIncluyeLasDosNuevas(): void
    {
        $variables = Publisher::variables();

        $this->assertArrayHasKey('{categorias_hashtags}', $variables, 'Las categorías como hashtags.');
        $this->assertArrayHasKey('{entradilla}', $variables, 'La entradilla.');
        $this->assertCount(13, $variables, 'Y la pantalla las enseña todas: las de siempre más estas dos.');
    }
}
