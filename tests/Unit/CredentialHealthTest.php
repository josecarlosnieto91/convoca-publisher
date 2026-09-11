<?php

/**
 * Credenciales que caducan solas.
 *
 * Facebook y LinkedIn caducan a los 60 días y el envío empieza a fallar sin que nadie haya
 * tocado nada. Lo que se puede saber con certeza es cuándo se comprobó por última vez que
 * funcionaba: con eso y lo que dura el token de esa red se avisa antes, no después.
 *
 * Lo que se fija aquí: los tramos (recién verificada, a punto, caducada), que una red cuyo
 * token no caduca no genera avisos falsos, y que la tarjeta del canal lo enseña.
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Admin;
    use ConvocaPublisher\Credential_Health;
    use ConvocaPublisher\Profile_Store;
    use PHPUnit\Framework\TestCase;

    final class CredentialHealthTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            \cp_test_reset();

            $_GET = [];
        }

        private function hace(int $dias): int
        {
            return time() - ($dias * DAY_IN_SECONDS);
        }

        // ── Los tramos ──────────────────────────────────────────────────────

        public function testRecienVerificadaEstaBien(): void
        {
            $this->assertSame('ok', Credential_Health::state('facebook', $this->hace(3)));
        }

        public function testAunqueSeaUnaRedQueCaducaNoSeAvisaElPrimerDia(): void
        {
            $this->assertFalse(Credential_Health::needs_attention('facebook', $this->hace(1)));
        }

        public function testAPuntoDeCaducarAvisa(): void
        {
            $this->assertSame('caduca-pronto', Credential_Health::state('facebook', $this->hace(46)));
            $this->assertTrue(Credential_Health::needs_attention('facebook', $this->hace(46)));
        }

        public function testPasadoElPlazoLoDice(): void
        {
            $this->assertSame('caducada', Credential_Health::state('facebook', $this->hace(61)));
            $this->assertSame('caducada', Credential_Health::state('linkedin', $this->hace(75)));
        }

        public function testUnaRedCuyoTokenNoCaducaNoDaAvisosFalsos(): void
        {
            $this->assertSame('sin-caducidad', Credential_Health::state('telegram', $this->hace(400)));
            $this->assertFalse(Credential_Health::needs_attention('telegram', $this->hace(400)));
        }

        public function testSinHaberlaComprobadoNuncaNoSeInventaUnaFecha(): void
        {
            $this->assertSame('sin-datos', Credential_Health::state('facebook', 0));
            $this->assertFalse(Credential_Health::needs_attention('facebook', 0));
        }

        public function testCadaRedTieneSuPlazo(): void
        {
            $this->assertSame(60, Credential_Health::lifetime('facebook'));
            $this->assertSame(60, Credential_Health::lifetime('linkedin'));
            $this->assertSame(0, Credential_Health::lifetime('telegram'));
            $this->assertSame(0, Credential_Health::lifetime('una-red-que-no-existe'));
        }

        public function testElMensajeDiceDesdeCuandoYCuantoDura(): void
        {
            $mensaje = Credential_Health::message('facebook', $this->hace(61));

            $this->assertStringContainsString('61', $mensaje, 'Desde cuándo no se comprueba.');
            $this->assertStringContainsString('60', $mensaje, 'Y cuánto dura el token.');
        }

        public function testLoQueVaLBienNoDiceNada(): void
        {
            $this->assertSame('', Credential_Health::message('facebook', $this->hace(2)), 'Sin avisos que no hacen falta.');
        }

        // ── En la tarjeta del canal ─────────────────────────────────────────

        private function tarjeta(int $diasDesdeLaVerificacion): array
        {
            $perfil = Profile_Store::create('facebook', 'Facebook — Página', [
                'convoca_publisher_facebook_token'   => 'TOKEN',
                'convoca_publisher_facebook_page_id' => '123',
            ]);

            $this->assertIsArray($perfil);

            $cuenta = \ConvocaPublisher\Plugin::accounts()[(string) $perfil['id']];

            $GLOBALS['_cp_test_options']['convoca_publisher_verify_status'][$perfil['id']] = [
                'success'     => true,
                'message'     => 'Conexión correcta',
                'time'        => wp_date('Y-m-d H:i:s', $this->hace($diasDesdeLaVerificacion)),
                'fingerprint' => Admin::channel_fingerprint($cuenta),
            ];

            return Admin::channel_status($cuenta);
        }

        public function testLaTarjetaAvisaCuandoLaCredencialPuedeHaberCaducado(): void
        {
            $estado = $this->tarjeta(61);

            $this->assertSame('reconnect', $estado['key']);
            $this->assertSame('Puede haber caducado', $estado['label']);
            $this->assertNotEmpty($estado['detail'], 'Y dice desde cuándo, para poder decidir.');
        }

        public function testLaTarjetaAvisaAntesDeQueCaducque(): void
        {
            $estado = $this->tarjeta(50);

            $this->assertSame('expiring', $estado['key']);
            $this->assertSame('Caduca pronto', $estado['label']);
        }

        public function testUnaCredencialRecienteSaleConfigurada(): void
        {
            $estado = $this->tarjeta(2);

            $this->assertSame('ok', $estado['key']);
            $this->assertSame('Configurado', $estado['label']);
        }
    }
}
