<?php
/**
 * Los errores de los canales dicen QUÉ HACER, no «Error desconocido».
 *
 * Defecto del gauntlet (pasada 8): cuando la API de una red no devolvía mensaje de error, el respaldo
 * que veía el usuario era «Error desconocido de X API.» — o directamente «Error desconocido», que no
 * dice ni qué ha pasado ni qué hacer. Ahora el respaldo nombra la red y dice qué intentar.
 *
 * El código propio se lee SIN comentarios: el comentario que explica el defecto nombra el literal.
 *
 * Prueba en negativo: con el literal puesto, falla y nombra el fichero.
 *
 * @package ConvocaPublisher\Tests\Unit
 */

namespace ConvocaPublisher\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MensajesDeErrorTest extends TestCase
{
    /** Textos que no pueden volver al código de este repo. */
    private const PROHIBIDOS = array(
        'Error desconocido',
    );

    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Ficheros propios con texto que ve el usuario.
     *
     * @return array<string, string> ruta relativa => código sin comentarios.
     */
    private function ficheros(): array
    {
        $raiz   = $this->raiz();
        $salida = array();

        foreach (array('includes', 'assets/js') as $dir) {
            $ruta = $raiz . '/' . $dir;
            if (!is_dir($ruta)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if (!$f->isFile()) {
                    continue;
                }
                if (!in_array($f->getExtension(), array('php', 'js'), true)) {
                    continue;
                }

                // Sin comentarios y con el tokenizador: un test que lee fuente no puede fallar
                // contra el comentario que explica el defecto.
                $src = (string) file_get_contents($f->getPathname());
                if ('php' === $f->getExtension()) {
                    $limpio = '';
                    foreach (token_get_all($src) as $token) {
                        if (is_array($token)) {
                            if (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
                                continue;
                            }
                            $limpio .= $token[1];
                        } else {
                            $limpio .= $token;
                        }
                    }
                    $src = $limpio;
                } else {
                    $src = (string) preg_replace('#/\*.*?\*/#s', '', $src);
                    $src = (string) preg_replace('#(^|\s)//[^\n]*#', '$1', $src);
                }

                $rel          = ltrim(str_replace($raiz, '', $f->getPathname()), '/');
                $salida[$rel] = $src;
            }
        }

        $this->assertNotEmpty($salida, 'No se ha encontrado código propio que revisar.');

        return $salida;
    }

    public function test_ningun_mensaje_prohibido_en_codigo_propio(): void
    {
        foreach ($this->ficheros() as $rel => $codigo) {
            foreach (self::PROHIBIDOS as $prohibido) {
                $this->assertStringNotContainsString(
                    $prohibido,
                    $codigo,
                    "El fichero {$rel} sigue con «{$prohibido}»: el respaldo tiene que decir qué hacer."
                );
            }
        }
    }

    public function test_los_respaldos_de_los_canales_nombran_la_red(): void
    {
        // Control positivo: los respaldos siguen ahí (si alguien borra el canal en vez de
        // arreglarlo, esto lo denuncia).
        $canales = glob($this->raiz() . '/includes/channels/class-*.php');
        $this->assertNotEmpty($canales, 'No encuentro los canales del publicador.');

        $con_respaldo = 0;
        foreach ($canales as $f) {
            if ('interface-channel.php' === basename($f)) {
                continue;
            }
            if (str_contains((string) file_get_contents($f), 'no ha explicado el fallo')) {
                $con_respaldo++;
            }
        }

        $this->assertGreaterThanOrEqual(6, $con_respaldo, 'Se han perdido los respaldos con texto útil en los canales.');
    }
}
