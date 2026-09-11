<?php

/**
 * Publicar en Instagram desde el canal de Facebook/Instagram.
 *
 * Lo que se fija aquí:
 *  · Instagram se publica de verdad (contenedor, y luego publicación), no se anuncia.
 *  · Sin imagen no se intenta: Instagram no admite publicaciones sin imagen.
 *  · Si Instagram falla, el envío NO es un fallo: el muro ya está publicado y reintentarlo
 *    lo duplicaría en Facebook. Se cuenta el motivo y se sigue.
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Channels\Facebook;
use PHPUnit\Framework\TestCase;

final class InstagramPublishTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();

        $_GET     = [];
        $_REQUEST = [];

        $GLOBALS['_cp_test_options'] = [
            'convoca_publisher_facebook_token'   => 'TOKEN',
            'convoca_publisher_facebook_page_id' => '123',
        ];
    }

    /** Encola una respuesta simulada de Meta (las peticiones salen en orden). */
    private function encolar(array $cuerpo, int $codigo = 200): void
    {
        $GLOBALS['_cp_test_http_queue'][] = [
            'body'     => (string) json_encode($cuerpo),
            'response' => ['code' => $codigo],
        ];
    }

    /** Las llamadas cuya URL acaba en ese trozo (para no confundir /media con /media_publish). */
    private function llamadas(string $sufijo): array
    {
        return array_values(array_filter(
            $GLOBALS['_cp_test_http'],
            static fn(array $llamada): bool => str_ends_with($llamada['url'], $sufijo)
        ));
    }

    public function testPublicaEnInstagramConContenedorYPublicacion(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_instagram_business_id'] = 'IG_ID';
        $this->encolar(['id' => 'FB1']);    // el muro
        $this->encolar(['id' => 'CONT1']);  // el contenedor
        $this->encolar(['id' => 'IG1']);    // la publicación

        $resultado = (new Facebook())->publish(7, 'Hola', 'https://ejemplo.test/a', 'https://ejemplo.test/i.jpg');

        $this->assertTrue($resultado['success']);
        $this->assertSame('facebook+instagram', $resultado['networks'], 'Solo se dice Instagram si se publicó en Instagram.');
        $this->assertSame('', $resultado['notice']);

        $contenedor = $this->llamadas('/media');
        $this->assertNotSame([], $contenedor, 'Se crea el contenedor.');
        $this->assertSame('https://ejemplo.test/i.jpg', $contenedor[0]['args']['body']['image_url'], 'Con la imagen destacada.');
        $this->assertSame('Hola', $contenedor[0]['args']['body']['caption'], 'Y con el mensaje.');

        $publicacion = $this->llamadas('/media_publish');
        $this->assertNotSame([], $publicacion, 'Y se publica el contenedor.');
        $this->assertSame('CONT1', $publicacion[0]['args']['body']['creation_id'], 'Con el identificador que devolvió Meta.');
    }

    public function testSinImagenNoSeIntentaYSeDicePorQue(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_instagram_business_id'] = 'IG_ID';
        $this->encolar(['id' => 'FB1']);

        $resultado = (new Facebook())->publish(7, 'Hola', 'https://ejemplo.test/a', '');

        $this->assertTrue($resultado['success'], 'El muro se publicó.');
        $this->assertSame('facebook', $resultado['networks'], 'Y no se dice Instagram, porque allí no se publicó nada.');
        $this->assertStringContainsString('imagen', $resultado['notice'], 'Se explica el motivo.');
        $this->assertSame([], $this->llamadas('/media'), 'Ni se llama a Instagram: sería una petición que Meta rechaza.');
    }

    public function testSiInstagramFallaElEnvioNoSeDaPorFallido(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_instagram_business_id'] = 'IG_ID';
        $this->encolar(['id' => 'FB1']);
        $this->encolar(['error' => ['message' => 'La cuenta no admite publicaciones']], 400);

        $resultado = (new Facebook())->publish(7, 'Hola', 'https://ejemplo.test/a', 'https://ejemplo.test/i.jpg');

        $this->assertTrue($resultado['success'], 'El muro está publicado: darlo por fallido lo duplicaría al reintentar.');
        $this->assertSame('facebook', $resultado['networks'], 'Y no se dice Instagram.');
        $this->assertStringContainsString('La cuenta no admite publicaciones', $resultado['notice'], 'Se cuenta lo que dijo Meta.');
    }

    public function testSinCuentaDeInstagramElCanalSoloPublicaEnFacebook(): void
    {
        $this->encolar(['id' => 'FB1']);

        $resultado = (new Facebook())->publish(7, 'Hola', 'https://ejemplo.test/a', 'https://ejemplo.test/i.jpg');

        $this->assertTrue($resultado['success']);
        $this->assertSame('facebook', $resultado['networks']);
        $this->assertSame('', $resultado['notice'], 'Sin cuenta configurada no hay nada que contar.');
        $this->assertSame([], $this->llamadas('/media'));
    }
}
