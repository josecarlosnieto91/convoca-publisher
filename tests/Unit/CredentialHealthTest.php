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

        public function testUnTokenQueDuraHorasSeNotaAlDiaSiguiente(): void
        {
            // Las dos redes cuyo token pegado a mano dura horas: si alguna vuelve a «no caduca»,
            // el aviso desaparece y el envío se pierde sin saber por qué.
            $this->assertSame(1, Credential_Health::lifetime('tiktok'), 'El access token de TikTok dura un día.');
            $this->assertSame(1, Credential_Health::lifetime('googlemybusiness'), 'El de Google dura una hora: con un día basta para saber que está muerto.');
            $this->assertSame('caducada', Credential_Health::state('tiktok', $this->hace(2)));
            $this->assertSame('caducada', Credential_Health::state('googlemybusiness', $this->hace(2)));
            $this->assertSame('ok', Credential_Health::state('tiktok', $this->hace(0)), 'Recién pegado, todavía vale.');
        }

        public function testElPlazoDeCadaRedEsUnaDecisionYNoUnOlvido(): void
        {
            $sin_plazo = array_diff(array_keys(\ConvocaPublisher\Plugin::networks()), Credential_Health::networks());

            $this->assertSame(
                [],
                array_values($sin_plazo),
                'Una red fuera de la tabla se trata como «no caduca» sin que nadie lo haya decidido.'
            );
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

        /**
         * Lo mismo, pero en un sitio que no viva en UTC: la fecha guardada es hora del sitio
         * (`current_time`), y convertirla mal desplaza el cálculo un día entero.
         */
        public function testElCalculoNoSeDesplazaPorLaZonaDelSitio(): void
        {
            $GLOBALS['_cp_test_timezone'] = 'Europe/Madrid';

            $perfil = Profile_Store::create('facebook', 'Facebook — Página', [
                'convoca_publisher_facebook_token'   => 'TOKEN',
                'convoca_publisher_facebook_page_id' => '123',
            ]);

            $this->assertIsArray($perfil);

            $cuenta = \ConvocaPublisher\Plugin::accounts()[(string) $perfil['id']];

            // El caso que muerde: justo pasados los 60 días. Si la fecha se convirtiera mal,
            // el desfase de la zona la dejaría por debajo del plazo y diría «caduca pronto».
            $casos = [
                ['segundos' => 2 * DAY_IN_SECONDS, 'clave' => 'ok'],
                ['segundos' => 61 * DAY_IN_SECONDS, 'clave' => 'reconnect'],
                ['segundos' => 60 * DAY_IN_SECONDS + 3600, 'clave' => 'reconnect'],
            ];

            foreach ($casos as ['segundos' => $segundos, 'clave' => $clave]) {
                $GLOBALS['_cp_test_options']['convoca_publisher_verify_status'][$perfil['id']] = [
                    'success'     => true,
                    'message'     => 'Conexión correcta',
                    // La hora que guardaría WordPress en ese sitio (no UTC).
                    'time'        => (new \DateTimeImmutable('@' . (time() - $segundos)))->setTimezone(new \DateTimeZone('Europe/Madrid'))->format('Y-m-d H:i:s'),
                    'fingerprint' => Admin::channel_fingerprint($cuenta),
                ];

                $this->assertSame(
                    $clave,
                    Admin::channel_status($cuenta)['key'],
                    sprintf('Con la credencial de hace %d s, en hora de Madrid.', $segundos)
                );
            }
        }

        public function testLaPantallaDeCanalesLoEnseniaDondeSeMiranLasCuentas(): void
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
                'time'        => wp_date('Y-m-d H:i:s', $this->hace(61)),
                'fingerprint' => Admin::channel_fingerprint($cuenta),
            ];

            $_GET = ['page' => 'convoca-publisher', 'tab' => 'channels'];

            ob_start();
            Admin::render_page();
            $html = (string) ob_get_clean();

            $this->assertStringContainsString(
                'Puede haber caducado',
                $html,
                'Si la credencial puede haber caducado, tiene que verse en la lista de cuentas.'
            );
        }

        public function testUnaCredencialRecienteSaleConfigurada(): void
        {
            $estado = $this->tarjeta(2);

            $this->assertSame('ok', $estado['key']);
            $this->assertSame('Configurado', $estado['label']);
        }
    }
}
