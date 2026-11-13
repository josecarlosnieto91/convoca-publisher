<?php

/**
 * Validación previa: lo que bloquea y lo que solo avisa.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Plugin;
use ConvocaPublisher\Preflight;
use ConvocaPublisher\Profile_Store;
use PHPUnit\Framework\TestCase;

final class PreflightTest extends TestCase
{
    private string $account = '';

    protected function setUp(): void
    {
        \cp_test_reset();
        Plugin::reset();

        $GLOBALS['_cp_test_options']           = [];
        $GLOBALS['_cp_test_postmeta']          = [];
        $GLOBALS['_cp_test_titles'][5]          = 'Entrada de prueba';
        $GLOBALS['_cp_test_status'][5]          = 'publish';
        $GLOBALS['_cp_test_attachment_url']     = 'https://example.com/imagen.jpg';
        $GLOBALS['_cp_test_attachment_meta']    = ['width' => 1080, 'height' => 1350];
        $GLOBALS['_cp_test_mime']               = 'image/jpeg';
        $GLOBALS['_cp_test_thumbnail_id']       = 10;

        $cuenta = Profile_Store::create('facebook', 'Facebook — Pruebas', [
            'convoca_publisher_facebook_token'       => 'TOKEN',
            'convoca_publisher_facebook_page_id'     => '123',
            'convoca_publisher_instagram_business_id' => '456',
        ]);

        $this->assertIsArray($cuenta);
        $this->account = (string) $cuenta['id'];

        Plugin::reset();
        parent::setUp();
    }

    public function test_sin_imagen_bloquea_instagram(): void
    {
        $GLOBALS['_cp_test_attachment_url'] = '';
        $GLOBALS['_cp_test_attachment_meta'] = [];

        $check = Preflight::validate(5, $this->account, false);

        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('Instagram exige una imagen', implode(' ', $check['blockers']));
    }

    public function test_webp_no_vale_para_instagram(): void
    {
        $GLOBALS['_cp_test_mime'] = 'image/webp';

        $check = Preflight::validate(5, $this->account, false);

        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('solo acepta JPEG', implode(' ', $check['blockers']));
    }

    public function test_una_imagen_jpeg_valida_pasa(): void
    {
        $check = Preflight::validate(5, $this->account, false);

        $this->assertTrue($check['ok']);
        $this->assertSame('instagram', $check['network']);
        $this->assertSame(1080, $check['image']['width']);
        $this->assertSame(1350, $check['image']['height']);
        $this->assertNotEmpty($check['warnings'], 'Instagram siempre avisa de que el pie no se puede editar.');
    }

    public function test_una_imagen_que_no_responde_bloquea_instagram(): void
    {
        $GLOBALS['_cp_test_head_response'] = ['response' => ['code' => 404]];

        $check = Preflight::validate(5, $this->account, true);

        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('devolvió 404', implode(' ', $check['blockers']));
    }

    public function test_sin_titulo_es_un_bloqueo(): void
    {
        $GLOBALS['_cp_test_titles'][5] = '';

        $check = Preflight::validate(5, $this->account, false);

        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('no tiene título', implode(' ', $check['blockers']));
    }
}
