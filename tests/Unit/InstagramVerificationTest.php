<?php

/**
 * Verificación de la cuenta de Instagram vinculada a la Página.
 *
 * Lo que se fija aquí, con el episodio del 09/10/2026 delante:
 *  · La comprobación le pregunta a la PÁGINA por su cuenta vinculada, no a la cuenta por su
 *    cuota de publicación: ese campo ya no existe y Meta responde 400, así que la pantalla
 *    enseñaba un ❌ por un fallo que no había.
 *  · El ID que trae la respuesta de la Página es el que se usa para publicar: si el configurado
 *    no es ese, se avisa con el correcto en vez de fallar al publicar.
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Channels\Facebook;
use PHPUnit\Framework\TestCase;

final class InstagramVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();

        $_GET     = [];
        $_REQUEST = [];

        $GLOBALS['_cp_test_options'] = [
            'convoca_publisher_facebook_token'   => 'TOKEN',
            'convoca_publisher_facebook_page_id' => '608790672316516',
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

    public function testNombraLaCuentaVinculadaCuandoElIdEsCorrecto(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_instagram_business_id'] = '17841470398177912';

        $this->encolar(['name' => 'Centro de ejemplo']);
        $this->encolar([
            'instagram_business_account' => ['id' => '17841470398177912', 'username' => 'ejemplocentrosocial'],
        ]);

        $resultado = (new Facebook())->verify_connection();

        $this->assertTrue($resultado['success']);
        $this->assertStringContainsString('@ejemplocentrosocial', $resultado['message']);
        $this->assertStringNotContainsString('❌', $resultado['message']);
    }

    public function testAvisaConElIdCorrectoCuandoElConfiguradoNoEsElDeLaCuenta(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_instagram_business_id'] = '31892307720360552';

        $this->encolar(['name' => 'Centro de ejemplo']);
        $this->encolar([
            'instagram_business_account' => ['id' => '17841470398177912', 'username' => 'ejemplocentrosocial'],
        ]);

        $resultado = (new Facebook())->verify_connection();

        $this->assertStringContainsString('❌', $resultado['message']);
        $this->assertStringContainsString('17841470398177912', $resultado['message']);
        $this->assertStringContainsString('31892307720360552', $resultado['message']);
    }

    public function testDiceCuandoLaPaginaNoTieneCuentaVinculada(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_instagram_business_id'] = '17841470398177912';

        $this->encolar(['name' => 'Centro de ejemplo']);
        $this->encolar(['id' => '608790672316516']);

        $resultado = (new Facebook())->verify_connection();

        $this->assertStringContainsString('⚠️', $resultado['message']);
        $this->assertStringContainsString('no tiene ninguna cuenta de Instagram', $resultado['message']);
    }

    public function testLaConsultaALaPaginaLlevaCredencial(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_instagram_business_id'] = '17841470398177912';

        $this->encolar(['name' => 'Centro de ejemplo']);
        $this->encolar([
            'instagram_business_account' => ['id' => '17841470398177912', 'username' => 'ejemplocentrosocial'],
        ]);

        (new Facebook())->verify_connection();

        $conInstagram = array_values(array_filter(
            (array) $GLOBALS['_cp_test_http'],
            static fn(array $llamada): bool => str_contains($llamada['url'], 'instagram_business_account')
        ));

        $this->assertNotEmpty($conInstagram, 'Hay que preguntarle a la página por su cuenta de Instagram.');

        // Sin credencial Meta responde «(#200) Provide valid app ID»: la consulta tiene que llevarla.
        $this->assertStringContainsString('access_token=', $conInstagram[0]['url']);
    }

    public function testNoPreguntaPorLaCuotaDePublicacionRetirada(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_instagram_business_id'] = '17841470398177912';

        $this->encolar(['name' => 'Centro de ejemplo']);
        $this->encolar([
            'instagram_business_account' => ['id' => '17841470398177912', 'username' => 'ejemplocentrosocial'],
        ]);

        (new Facebook())->verify_connection();

        foreach ((array) $GLOBALS['_cp_test_http'] as $llamada) {
            $this->assertStringNotContainsString(
                'content_publishing_limit',
                $llamada['url'],
                'No debe volver a pedirse el campo retirado: Meta responde 400.'
            );
        }

        $this->assertNotEmpty($GLOBALS['_cp_test_http']);
    }
}
