<?php

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Channels\Googlemybusiness;
use ConvocaPublisher\Profile_Store;
use PHPUnit\Framework\TestCase;

/**
 * GMB: credenciales por perfil, renovación del token e imágenes que la API acepta.
 */
final class GoogleMyBusinessTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_cp_test_options']     = [];
        $GLOBALS['_cp_test_http']        = [];
        $GLOBALS['_cp_test_http_queue']  = [];
        $GLOBALS['_cp_test_transients']  = [];
        $GLOBALS['_cp_test_postmeta']    = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_cp_test_options'],
            $GLOBALS['_cp_test_http'],
            $GLOBALS['_cp_test_http_queue'],
            $GLOBALS['_cp_test_transients'],
            $GLOBALS['_cp_test_postmeta'],
            $GLOBALS['_cp_test_attachment_urls'],
            $GLOBALS['_cp_test_original_urls'],
            $GLOBALS['_cp_test_attached_files'],
            $GLOBALS['_cp_test_image_editor'],
            $GLOBALS['_cp_test_upload_dir']
        );
    }

    /**
     * @return array<string, string>
     */
    private function ajustes(): array
    {
        return [
            'convoca_publisher_gmb_token'    => 'ya29.token-de-prueba',
            'convoca_publisher_gmb_location' => 'accounts/1/locations/2',
        ];
    }

    /**
     * Con el canal configurado por el panel (perfil) y sin opciones globales, la clase del
     * canal tiene que declararse disponible: antes decía «no configurado» (publisher#26).
     */
    public function test_lee_las_credenciales_del_perfil_sin_opciones_globales(): void
    {
        Profile_Store::create('googlemybusiness', 'Cuenta GMB', $this->ajustes());

        $canal = new Googlemybusiness();

        $this->assertTrue($canal->is_available(), 'las credenciales viven en el perfil, no en opciones globales');
    }

    /**
     * Un canal instanciado a mano con los ajustes de su cuenta no depende de nada global.
     */
    public function test_los_ajustes_inyectados_mandan_sobre_las_opciones_globales(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_gmb_token']    = 'token-global-viejo';
        $GLOBALS['_cp_test_options']['convoca_publisher_gmb_location'] = 'accounts/9/locations/9';

        $canal = new Googlemybusiness($this->ajustes());
        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 200], 'body' => '{"name":"accounts/1/locations/2/localPosts/3"}'],
        ];

        $resultado = $canal->publish(10, 'Mensaje', 'https://example.com/?p=10');

        $this->assertTrue($resultado['success']);
        $this->assertStringContainsString('/accounts/1/locations/2/localPosts', $GLOBALS['_cp_test_http'][0]['url']);
        $this->assertSame('Bearer ya29.token-de-prueba', $GLOBALS['_cp_test_http'][0]['args']['headers']['Authorization']);
    }

    /**
     * Con refresh token se pide un access token nuevo y se reutiliza mientras vive.
     */
    public function test_renueva_el_token_con_el_refresh_token_y_lo_reutiliza(): void
    {
        $ajustes = $this->ajustes() + [
            'convoca_publisher_gmb_refresh_token'   => '1//refresh',
            'convoca_publisher_gmb_client_id'       => 'cliente.apps.googleusercontent.com',
            'convoca_publisher_gmb_client_secret'   => 'secreto',
        ];

        $canal = new Googlemybusiness($ajustes);

        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 200], 'body' => '{"access_token":"ya29.nuevo","expires_in":3600}'],
            ['response' => ['code' => 200], 'body' => '{"name":"accounts/1/locations/2/localPosts/3"}'],
            ['response' => ['code' => 200], 'body' => '{"name":"accounts/1/locations/2/localPosts/4"}'],
        ];

        $this->assertTrue($canal->publish(10, 'Mensaje', 'https://example.com/?p=10')['success']);
        $this->assertTrue($canal->publish(11, 'Otro', 'https://example.com/?p=11')['success']);

        $this->assertCount(3, $GLOBALS['_cp_test_http'], 'la segunda publicación no vuelve a pedir token');
        $this->assertStringContainsString('oauth2.googleapis.com/token', $GLOBALS['_cp_test_http'][0]['url']);
        $this->assertSame('refresh_token', $GLOBALS['_cp_test_http'][0]['args']['body']['grant_type']);
        $this->assertSame('Bearer ya29.nuevo', $GLOBALS['_cp_test_http'][1]['args']['headers']['Authorization']);
    }

    /**
     * Un 401 se explica como token caducado, no como un fallo opaco.
     */
    public function test_un_401_dice_que_el_token_caduco(): void
    {
        $canal = new Googlemybusiness($this->ajustes());
        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 401], 'body' => '{"error":{"message":"Unauthenticated"}}'],
        ];

        $resultado = $canal->publish(10, 'Mensaje', 'https://example.com/?p=10');

        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('caducado', $resultado['error']);
        $this->assertStringContainsString('refresh token', $resultado['error']);
    }

    /**
     * Un 400 lleva el status y los detalles de Google, no solo el mensaje genérico.
     */
    public function test_un_400_incluye_el_detalle_de_google(): void
    {
        $canal = new Googlemybusiness($this->ajustes());
        $GLOBALS['_cp_test_http_queue'] = [
            [
                'response' => ['code' => 400],
                'body'     => '{"error":{"message":"Request contains an invalid argument.","status":"INVALID_ARGUMENT","details":[{"reason":"INVALID_MEDIA_FORMAT","field":"media"}]}}',
            ],
        ];

        $resultado = $canal->publish(10, 'Mensaje', 'https://example.com/?p=10');

        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('INVALID_ARGUMENT', $resultado['error']);
        $this->assertStringContainsString('INVALID_MEDIA_FORMAT', $resultado['error']);
        $this->assertStringContainsString('media', $resultado['error']);
    }

    /**
     * Un webp con original jpeg se publica con el original.
     */
    public function test_un_webp_usa_el_original_jpeg_del_adjunto(): void
    {
        $GLOBALS['_cp_test_attachment_urls'] = ['https://example.com/uploads/foto.webp' => 77];
        $GLOBALS['_cp_test_original_urls']   = [77 => 'https://example.com/uploads/foto.jpg'];

        $imagen = Googlemybusiness::prepare_image_url('https://example.com/uploads/foto.webp');

        $this->assertSame('https://example.com/uploads/foto.jpg', $imagen['url']);
        $this->assertSame('', $imagen['notice']);
    }

    /**
     * Si no hay forma de conseguir JPEG, se publica sin foto y se avisa en el historial.
     */
    public function test_sin_jpeg_se_publica_sin_foto_y_con_aviso(): void
    {
        $GLOBALS['_cp_test_attachment_urls'] = ['https://example.com/uploads/foto.webp' => 77];
        $GLOBALS['_cp_test_original_urls']   = [77 => 'https://example.com/uploads/foto.webp'];

        $canal = new Googlemybusiness($this->ajustes());
        $GLOBALS['_cp_test_http_queue'] = [
            ['response' => ['code' => 200], 'body' => '{"name":"accounts/1/locations/2/localPosts/3"}'],
        ];

        $resultado = $canal->publish(10, 'Mensaje', 'https://example.com/?p=10', 'https://example.com/uploads/foto.webp');
        $cuerpo    = json_decode((string) $GLOBALS['_cp_test_http'][0]['args']['body'], true);

        $this->assertTrue($resultado['success'], 'la publicación no puede fallar por la imagen');
        $this->assertArrayNotHasKey('media', $cuerpo);
        $this->assertStringContainsString('sin foto', (string) ($resultado['notice'] ?? ''));
    }

    /**
     * Si el original también es webp, se genera una copia JPEG y se usa.
     */
    public function test_si_hace_falta_genera_una_copia_jpeg(): void
    {
        $base = sys_get_temp_dir() . '/gmb-' . uniqid();
        mkdir($base . '/uploads', 0777, true);
        file_put_contents($base . '/uploads/foto.webp', 'webp');

        $GLOBALS['_cp_test_upload_dir']      = ['basedir' => $base . '/uploads', 'baseurl' => 'https://example.com/uploads'];
        $GLOBALS['_cp_test_attachment_urls'] = ['https://example.com/uploads/foto.webp' => 77];
        $GLOBALS['_cp_test_original_urls']   = [77 => 'https://example.com/uploads/foto.webp'];
        $GLOBALS['_cp_test_attached_files']  = [77 => $base . '/uploads/foto.webp'];
        $GLOBALS['_cp_test_image_editor']    = new EditorFalso();

        $this->assertFileExists($base . '/uploads/foto.webp');

        $imagen = Googlemybusiness::prepare_image_url('https://example.com/uploads/foto.webp');

        $this->assertSame('https://example.com/uploads/foto-gmb.jpg', $imagen['url']);
        $this->assertSame('', $imagen['notice']);
    }
}

/**
 * Editor de imágenes mínimo: guarda siempre un JPEG en la ruta pedida.
 */
final class EditorFalso
{
    public function set_quality(int $calidad): void
    {
    }

    /**
     * @return array{path: string, mime_type: string}
     */
    public function save(string $ruta, string $mime = ''): array
    {
        return ['path' => $ruta, 'mime_type' => 'image/jpeg'];
    }
}
