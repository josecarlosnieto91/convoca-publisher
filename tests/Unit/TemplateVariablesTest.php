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
 * Aquí no hay ninguna trampa, y eso también se comprueba: `{categorias}` lleva la llave de
 * cierre, así que NO es prefijo de `{categorias_hashtags}`. La prueba fija que las dos
 * conviven sin comerse nada y que el motor va de la variable más larga a la más corta, para
 * que siga siendo verdad si algún día se añade un nombre corto que sí sea prefijo.
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

    private function entrada(string $contenido = 'Texto de la entrada.', string $titulo = 'Taller de huerto'): \WP_Post
    {
        $post               = new \WP_Post((object) []);
        $post->ID           = 7;
        $post->post_title   = $titulo;
        $post->post_content = $contenido;
        $post->post_author  = 3;

        return $post;
    }

    private function sustituir(string $plantilla, string $contenido = 'Texto de la entrada.', string $red = '', string $titulo = 'Taller de huerto'): string
    {
        return Publisher::instance()->render_template($this->entrada($contenido, $titulo), $plantilla, '', '', $red);
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
        $this->assertStringNotContainsString('_hashtags}', $conHashtags, 'Sin restos: nada se come el prefijo de la otra.');
        $this->assertStringContainsString('#huertourbano', $conHashtags, 'Y la segunda igual: una sola palabra.');
        $this->assertStringContainsString('Taller de huerto', $conNombres, 'Y los nombres siguen saliendo como nombres.');
    }

    public function testElTituloEnNegritaSoloDondeLaRedAceptaFormato(): void
    {
        $conFormato  = $this->sustituir('{titulo_negrita}', 'Texto.', 'telegram');
        $sinFormato  = $this->sustituir('{titulo_negrita}', 'Texto.', 'twitter');

        $this->assertStringContainsString('<b>Taller de huerto</b>', $conFormato, 'Donde se acepta formato, sale en negrita.');
        $this->assertSame('Taller de huerto', $sinFormato, 'Y donde no, el título tal cual: nada de etiquetas a la vista.');
    }

    public function testEnLasRedesConFormatoLosValoresSeEscapan(): void
    {
        // Un `&` o un `<` en el título rompen el parseo HTML de Telegram y la red rechaza el
        // mensaje entero. Se escapa solo donde hay formato: en las demás, el texto va tal cual.
        $con = $this->sustituir('{title}', 'Texto.', 'telegram', 'Pan & vino <gratis>');
        $sin = $this->sustituir('{title}', 'Texto.', 'twitter', 'Pan & vino <gratis>');

        $this->assertStringContainsString('&amp;', $con, 'Donde hay formato, se escapa: ' . $con);
        $this->assertStringNotContainsString('<gratis>', $con, 'Y no queda una etiqueta suelta.');
        $this->assertSame('Pan & vino <gratis>', $sin, 'Donde no hay formato, el texto va tal cual.');
    }

    public function testLosHashtagsRepetidosSalenUnaSolaVez(): void
    {
        // Pasó de verdad en la prueba en producción: dos etiquetas de Lugg daban el mismo
        // hashtag y salía repetido en el mensaje. Lo ven las etiquetas de verdad, no las
        // inventadas: basta con que dos normalicen igual.
        $GLOBALS['_cp_test_tags'][7] = ['Centro Social Los Lugg', 'centro social los lugg', 'asturias'];

        $mensaje = $this->sustituir('{hashtags}');

        $this->assertSame(1, substr_count($mensaje, '#centrosocialloslugg'), 'El hashtag repetido sale una sola vez: ' . $mensaje);
        $this->assertStringContainsString('#asturias', $mensaje, 'Y los demás siguen saliendo.');
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
        $this->assertCount(14, $variables, 'Y la pantalla las enseña todas: las de siempre más estas dos.');
    }
}
