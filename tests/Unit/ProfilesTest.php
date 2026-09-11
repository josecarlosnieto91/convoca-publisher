<?php

/**
 * Cuentas (perfiles): varias cuentas por red.
 *
 * Lo que se fija aquí:
 *  · La migración de «un token por red» a cuentas: una cuenta por red que tuviera algo
 *    configurado, con el **id de la red** (para que lo ya publicado, la cola y el
 *    historial sigan resolviendo) y sin borrar las opciones antiguas (marcha atrás).
 *  · Que cada cuenta publica y verifica con **sus** credenciales, aunque la opción global
 *    esté vacía y haya otra cuenta de la misma red al lado.
 *  · Que los tokens se guardan cifrados, el límite de cuentas por red y el borrado.
 *  · Que la plantilla de la cuenta manda, y si no tiene, la de su red (no la genérica).
 */

namespace ConvocaPublisher\Tests {

    use ConvocaPublisher\Channel_Profile;
    use ConvocaPublisher\Channels\Facebook;
    use ConvocaPublisher\Channels\Telegram;
    use ConvocaPublisher\Plugin;
    use ConvocaPublisher\Profile_Store;
    use ConvocaPublisher\Publisher;
    use PHPUnit\Framework\TestCase;

    final class ProfilesTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            $GLOBALS['_cp_test_options'] = [];
            $GLOBALS['_cp_test_http']    = [];
            $GLOBALS['_cp_test_filters'] = [];
        }

        /**
         * @return array<string, \ConvocaPublisher\Channels\ChannelInterface>
         */
        private function redes(): array
        {
            return Plugin::networks();
        }

        /**
         * @param array<string, string> $settings
         *
         * @return array<string, mixed>
         */
        private function crearCuenta(string $network, string $name, array $settings = [], string $template = ''): array
        {
            $cuenta = Profile_Store::create($network, $name, $settings, $template);

            $this->assertIsArray($cuenta, 'No se pudo crear la cuenta.');

            return $cuenta;
        }

        // ── Migración ───────────────────────────────────────────────────────

        public function testUnTokenAntiguoSeConvierteEnUnaCuenta(): void
        {
            update_option('convoca_publisher_telegram_token', 'TOKEN-VIEJO');
            update_option('convoca_publisher_telegram_chat_id', '-100');
            update_option('convoca_publisher_telegram_template', '{title} propio');

            Profile_Store::migrate($this->redes());
            $accounts = Profile_Store::all();

            $this->assertCount(1, $accounts);
            $this->assertSame('telegram', $accounts[0]['id'], 'El id de la red: así la cola y el historial siguen resolviendo.');
            $this->assertSame('telegram', $accounts[0]['channel']);
            $this->assertSame('Telegram', $accounts[0]['name']);
            $this->assertSame('TOKEN-VIEJO', $accounts[0]['settings']['convoca_publisher_telegram_token']);
            $this->assertSame('-100', $accounts[0]['settings']['convoca_publisher_telegram_chat_id']);
            $this->assertSame('{title} propio', $accounts[0]['template']);

            // Las opciones antiguas siguen ahí: la marcha atrás es volver a la versión anterior.
            $this->assertSame('TOKEN-VIEJO', get_option('convoca_publisher_telegram_token'));
        }

        public function testUnaRedSinNadaConfiguradoNoInventaCuenta(): void
        {
            update_option('convoca_publisher_telegram_token', 'TOKEN');

            Profile_Store::migrate($this->redes());
            $accounts = Profile_Store::all();

            $this->assertCount(1, $accounts);
            $this->assertSame('telegram', $accounts[0]['id']);
        }

        public function testLaMigracionSoloSeHaceUnaVez(): void
        {
            update_option('convoca_publisher_telegram_token', 'TOKEN');

            Profile_Store::migrate($this->redes());
            update_option('convoca_publisher_mastodon_token', 'OTRO');
            Profile_Store::migrate($this->redes());

            $this->assertCount(1, Profile_Store::all(), 'Una red configurada después de migrar no crea una cuenta por su cuenta.');
            $this->assertSame(Profile_Store::FORMAT_VERSION, (int) get_option(Profile_Store::MIGRATED_OPTION));
        }

        // ── Credenciales de cada cuenta ─────────────────────────────────────

        public function testLosTokensSeGuardanCifrados(): void
        {
            $this->crearCuenta('telegram', 'Telegram — Pruebas', [
                'convoca_publisher_telegram_token'   => 'SECRETO',
                'convoca_publisher_telegram_chat_id' => '-100',
            ]);

            $raw = get_option(Profile_Store::OPTION);

            $this->assertStringStartsWith('convoca_publisher_enc:', $raw[0]['settings']['convoca_publisher_telegram_token']);
            $this->assertStringNotContainsString('SECRETO', (string) wp_json_encode($raw), 'El token no puede quedar en claro en la base de datos.');
            $this->assertSame('SECRETO', Profile_Store::all()[0]['settings']['convoca_publisher_telegram_token']);
            $this->assertSame('-100', $raw[0]['settings']['convoca_publisher_telegram_chat_id'], 'El chat no es un secreto: se guarda tal cual.');
        }

        public function testUnaCuentaUsaSusCredencialesAunqueLaOpcionGlobalEsteVacia(): void
        {
            $cuenta = $this->crearCuenta('telegram', 'Telegram — Pruebas', [
                'convoca_publisher_telegram_token'   => 'TOKEN-CUENTA',
                'convoca_publisher_telegram_chat_id' => '-100',
            ]);

            $channel = new Channel_Profile($cuenta, new Telegram());

            $this->assertTrue($channel->is_available());
            $this->assertSame('', (string) get_option('convoca_publisher_telegram_token', ''), 'La opción global no se toca.');
        }

        public function testCadaCuentaVerificaConSuPropioToken(): void
        {
            $a = $this->crearCuenta('telegram', 'Telegram — A', [
                'convoca_publisher_telegram_token'   => 'TOKEN-A',
                'convoca_publisher_telegram_chat_id' => '-100',
            ]);
            $b = $this->crearCuenta('telegram', 'Telegram — B', [
                'convoca_publisher_telegram_token'   => 'TOKEN-B',
                'convoca_publisher_telegram_chat_id' => '-200',
            ]);

            (new Channel_Profile($a, new Telegram()))->verify_connection();
            (new Channel_Profile($b, new Telegram()))->verify_connection();

            $urls = array_column($GLOBALS['_cp_test_http'], 'url');

            $this->assertCount(2, $urls);
            $this->assertStringContainsString('botTOKEN-A/getMe', $urls[0]);
            $this->assertStringContainsString('botTOKEN-B/getMe', $urls[1]);
        }

        public function testFueraDeLaLlamadaElAjusteDeLaCuentaNoSeFiltra(): void
        {
            $a = $this->crearCuenta('telegram', 'Telegram — A', ['convoca_publisher_telegram_token' => 'TOKEN-A']);
            $channel = new Channel_Profile($a, new Telegram());

            $channel->is_available();

            $this->assertSame('', (string) get_option('convoca_publisher_telegram_token', ''));
        }

        // ── Cuentas: crear, listar, borrar ──────────────────────────────────

        public function testLosIdsSeRepitenSinPisar(): void
        {
            $primera = $this->crearCuenta('telegram', 'Telegram');
            $segunda = $this->crearCuenta('telegram', 'Telegram');

            $this->assertSame('telegram', $primera['id']);
            $this->assertSame('telegram-2', $segunda['id']);
        }

        public function testElLimiteDeCuentasPorRed(): void
        {
            for ($i = 1; $i <= Profile_Store::LIMIT_PER_NETWORK; ++$i) {
                $this->crearCuenta('telegram', 'Cuenta ' . $i);
            }

            $this->assertFalse(Profile_Store::create('telegram', 'Una más'), 'No se puede pasar del límite.');
            $this->assertSame(Profile_Store::LIMIT_PER_NETWORK, Profile_Store::count_for_channel('telegram'));
            $this->assertNotFalse(Profile_Store::create('facebook', 'Otra red sí'), 'El límite es por red, no global.');
        }

        public function testBorrarUnaCuentaNoTocaLasDemas(): void
        {
            $a = $this->crearCuenta('telegram', 'Telegram — A');
            $b = $this->crearCuenta('telegram', 'Telegram — B');
            $this->crearCuenta('mastodon', 'Mastodon');

            $this->assertTrue(Profile_Store::delete($a['id']));
            $this->assertNull(Profile_Store::find($a['id']));
            $this->assertNotNull(Profile_Store::find($b['id']));
            $this->assertSame(1, Profile_Store::count_for_channel('mastodon'));
            $this->assertFalse(Profile_Store::delete('no-existe'));
        }

        public function testLasCuentasSeListanPorSuRed(): void
        {
            $this->crearCuenta('telegram', 'Telegram — Centro Social');
            $this->crearCuenta('telegram', 'Telegram — Socios');
            $this->crearCuenta('mastodon', 'Mastodon');

            $this->assertCount(2, Profile_Store::for_channel('telegram'));
            $this->assertSame(['Telegram — Centro Social', 'Telegram — Socios'], array_column(Profile_Store::for_channel('telegram'), 'name'));
        }

        // ── Plantillas ──────────────────────────────────────────────────────

        public function testLaPlantillaDeLaCuentaManda(): void
        {
            $cuenta = $this->crearCuenta('facebook', 'Facebook — Página', [], '{title} PROPIA');
            $publisher = new Publisher([$cuenta['id'] => new Channel_Profile($cuenta, new Facebook())]);

            $message = $publisher->get_channel_message(1, $cuenta['id']);

            $this->assertStringContainsString('PROPIA', $message);
            $this->assertStringContainsString('Test Post', $message);
        }

        public function testSinPlantillaPropiaSeUsaLaDeSuRed(): void
        {
            update_option('convoca_publisher_facebook_template', '{title} DE LA RED');
            $cuenta = $this->crearCuenta('facebook', 'Facebook — Grupo');
            $publisher = new Publisher([$cuenta['id'] => new Channel_Profile($cuenta, new Facebook())]);

            $message = $publisher->get_channel_message(1, $cuenta['id']);

            $this->assertStringContainsString('DE LA RED', $message);
        }

        public function testSinPlantillasSeUsaLaDeSuRedNoLaGenerica(): void
        {
            // Telegram tiene su plantilla por defecto; una cuenta de Telegram debe usarla,
            // no la genérica (el id de la cuenta no es el de la red).
            $cuenta = $this->crearCuenta('telegram', 'Telegram — Centro Social');
            $publisher = new Publisher([$cuenta['id'] => new Channel_Profile($cuenta, new Telegram())]);

            $message = $publisher->get_channel_message(1, $cuenta['id']);

            $this->assertStringContainsString('Test Post', $message);
            $this->assertStringContainsString('https://example.com/?p=1', $message);
        }
    }
}
