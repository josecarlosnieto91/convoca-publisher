<?php
/**
 * La guía de configuración debe explicar los trámites que bloquean de verdad.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Vista_Guia;
use PHPUnit\Framework\TestCase;

/**
 * Guía de configuración de canales.
 */
final class VistaGuiaTest extends TestCase {

	/**
	 * Pinta la guía y devuelve el HTML.
	 *
	 * @return string
	 */
	private function html(): string {
		ob_start();
		Vista_Guia::render();
		return (string) ob_get_clean();
	}

	/**
	 * La guía también explica caducidades y límites de las demás redes.
	 */
	public function test_guia_de_caducidades(): void {
		$html = $this->html();

		$this->assertStringContainsString( '60 días', $html );
		$this->assertStringContainsString( '1 hora', $html );
		$this->assertStringContainsString( '1024', $html );
	}

	/**
	 * Facebook/Instagram: vínculo, permisos, ID y requisitos de imagen.
	 */
	public function test_guia_de_instagram_completa(): void {
		$html = $this->html();

		$this->assertStringContainsString( 'Cuentas vinculadas', $html );
		$this->assertStringContainsString( 'instagram_business_account', $html );
		$this->assertStringContainsString( 'instagram_content_publish', $html );
		$this->assertStringContainsString( 'JPEG', $html );
		$this->assertStringContainsString( '4:5', $html );
	}

	/**
	 * Google My Business: allowlist, tres APIs, scope y caducidad.
	 */
	public function test_guia_de_google_my_business_completa(): void {
		$html = $this->html();

		$this->assertStringContainsString( 'mybusinessaccountmanagement.googleapis.com', $html );
		$this->assertStringContainsString( 'mybusinessbusinessinformation.googleapis.com', $html );
		$this->assertStringContainsString( 'business.manage', $html );
		$this->assertStringContainsString( '60 minutos', $html );
		$this->assertStringContainsString( 'quota_limit_value', $html );
	}
}
