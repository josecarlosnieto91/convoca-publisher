<?php

/**
 * Clase inventada en el namespace de los canales para comprobar que el registro no
 * se traga cualquier cosa que viva en esa carpeta: solo entran canales de verdad.
 */

namespace ConvocaPublisher\Channels {
    class AyudanteDePrueba
    {
        public function get_id(): string
        {
            return 'ayudante';
        }
    }

    /**
     * Base abstracta inventada: un canal a medias no debe registrarse (hoy no hay
     * ninguna, pero una base compartida entra sola en la carpeta).
     */
    abstract class CanalAbstractoDePrueba implements ChannelInterface
    {
        public function get_id(): string
        {
            return 'abstracto';
        }

        public function get_name(): string
        {
            return 'Abstracto';
        }

        public function is_available(): bool
        {
            return false;
        }

        public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
        {
            return ['success' => false, 'error' => 'abstracto'];
        }

        public function get_settings_fields(): array
        {
            return [];
        }

        public function validate_settings(array $settings): array
        {
            return [];
        }

        public function verify_connection(): array
        {
            return [];
        }
    }
}

namespace ConvocaPublisher\Tests {
    use PHPUnit\Framework\TestCase;

    /**
     * Registro de canales.
     *
     * Aquí se prueban tres cosas que hasta ahora nadie miraba, y por eso el plugin
     * llegó a producción con **cero canales**: ni publicaba ni dejaba configurar nada.
     *
     * 1. El nombre de clase derivado tiene que llevar su mayúscula real. PHP es
     *    insensible a mayúsculas al declarar, pero el classmap de Composer no: si el
     *    registro pide «…\facebook», no lo encuentra y el canal desaparece.
     * 2. El registro devuelve los siete canales, pasando por el mismo camino que usa
     *    el plugin (antes las pruebas construían las clases a mano y el registro
     *    quedaba sin cubrir).
     * 3. En la carpeta de canales solo entran canales: una clase que no implementa la
     *    interfaz (o la propia interfaz) no se registra.
     */
    class ChannelRegistryTest extends TestCase
    {
        private const ESPERADOS = ['facebook', 'linkedin', 'twitter', 'tiktok', 'googlemybusiness', 'telegram', 'mastodon'];

        protected function setUp(): void
        {
            if (!class_exists(\ConvocaPublisher\Plugin::class)) {
                require_once CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/class-plugin.php';
            }
        }

        public function testDerivedClassNamesKeepTheirRealCase(): void
        {
            $names = \ConvocaPublisher\Plugin::channel_class_names();

            $this->assertNotEmpty($names, 'No se ha derivado ningún nombre de canal.');

            foreach ($names as $name) {
                $short = substr($name, strrpos($name, '\\') + 1);

                $this->assertNotSame(
                    strtolower($short),
                    $short,
                    "«{$name}» se pide en minúsculas: el classmap de Composer no lo resolverá."
                );

                $this->assertTrue(class_exists($name), "No se puede cargar «{$name}».");

                $declared = (new \ReflectionClass($name))->getName();

                $this->assertSame(
                    $name,
                    $declared,
                    'El nombre derivado no coincide letra a letra con la clase declarada.'
                );
            }
        }

        public function testDiscoveryReturnsEveryChannel(): void
        {
            $channels = \ConvocaPublisher\Plugin::discover_channels();

            $this->assertCount(
                count(self::ESPERADOS),
                $channels,
                'El registro debe devolver los siete canales, no ' . count($channels) . '.'
            );

            foreach (self::ESPERADOS as $id) {
                $this->assertArrayHasKey($id, $channels, "Falta el canal «{$id}».");
            }

            foreach ($channels as $id => $channel) {
                $this->assertInstanceOf(\ConvocaPublisher\Channels\ChannelInterface::class, $channel);
                $this->assertSame($id, $channel->get_id(), 'La clave del registro debe ser el id del canal.');
                $this->assertNotSame('', $channel->get_name());
                $this->assertNotEmpty($channel->get_settings_fields(), "«{$id}» no declara sus campos.");
            }
        }

        public function testOnlyRealChannelsAreRegistered(): void
        {
            $channels = \ConvocaPublisher\Plugin::discover_channels();

            $this->assertArrayNotHasKey(
                'ayudante',
                $channels,
                'Una clase del namespace que no implementa la interfaz no es un canal.'
            );
            $this->assertArrayNotHasKey(
                'channelinterface',
                $channels,
                'La interfaz tampoco: no puede colarse en el registro.'
            );
            $this->assertArrayNotHasKey(
                'abstracto',
                $channels,
                'Una base abstracta de canal no se instancia ni se registra.'
            );
        }

        /**
         * El registro no puede depender del classmap de Composer.
         *
         * Si alguien añade un canal y nadie regenera el classmap, su fichero tiene que
         * cargarse igual. Se comprueba en un proceso aparte (aquí las clases ya están
         * cargadas) con el autoloader real y un canal inventado que el classmap no
         * conoce.
         */
        public function testANewChannelIsFoundEvenIfTheClassmapIsStale(): void
        {
            $autoload = CONVOCA_PUBLISHER_PLUGIN_DIR . 'vendor/autoload.php';

            if (!file_exists($autoload)) {
                $this->markTestSkipped('Sin `composer install` no hay autoloader que probar.');
            }

            $dir = sys_get_temp_dir() . '/hermes-verify-canal-' . uniqid();
            mkdir($dir . '/includes/channels', 0777, true);

            foreach (glob(CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/*.php') ?: [] as $file) {
                copy($file, $dir . '/includes/channels/' . basename($file));
            }

            file_put_contents($dir . '/includes/channels/class-inventado.php', <<<'PHP'
                <?php
                namespace ConvocaPublisher\Channels;
                class Inventado implements ChannelInterface
                {
                    public function get_id(): string { return 'inventado'; }
                    public function get_name(): string { return 'Inventado'; }
                    public function is_available(): bool { return true; }
                    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array { return ['success' => true]; }
                    public function get_settings_fields(): array { return ['inventado_token' => ['label' => 'Token']]; }
                    public function validate_settings(array $settings): array { return []; }
                    public function verify_connection(): array { return ['success' => true]; }
                }
                PHP);

            $code = 'define("ABSPATH", true);'
                . ' define("CONVOCA_PUBLISHER_PLUGIN_DIR", ' . var_export($dir . '/', true) . ');'
                . ' require ' . var_export($autoload, true) . ';'
                . ' $c = \ConvocaPublisher\Plugin::discover_channels();'
                . ' echo count($c) . ":" . implode(",", array_keys($c));';

            $out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1');

            foreach (glob($dir . '/includes/channels/*.php') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir . '/includes/channels');
            rmdir($dir . '/includes');
            rmdir($dir);

            $this->assertStringStartsWith('8:', $out, 'Un canal nuevo debe cargarse aunque el classmap no lo conozca: ' . $out);
            $this->assertStringContainsString('inventado', $out);
        }

        /**
         * Ni una base abstracta ni una clase anónima pueden acabar en el registro: no se
         * pueden instanciar. Se prueban en ficheros de canales de verdad (carpeta temporal
         * con el nombre que el registro espera), que es el único caso en el que la ruta del
         * fichero no las descarta por sí sola.
         */
        public function testAnAbstractOrAnonymousChannelClassIsNotRegistered(): void
        {
            $autoload = CONVOCA_PUBLISHER_PLUGIN_DIR . 'vendor/autoload.php';

            if (!file_exists($autoload)) {
                $this->markTestSkipped('Sin `composer install` no hay autoloader que probar.');
            }

            $dir = sys_get_temp_dir() . '/hermes-verify-base-' . uniqid();
            mkdir($dir . '/includes/channels', 0777, true);

            foreach (glob(CONVOCA_PUBLISHER_PLUGIN_DIR . 'includes/channels/*.php') ?: [] as $file) {
                copy($file, $dir . '/includes/channels/' . basename($file));
            }

            $canal = <<<'PHP'
                    public function get_id(): string { return 'ID_AQUI'; }
                    public function get_name(): string { return 'Prueba'; }
                    public function is_available(): bool { return true; }
                    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array { return ['success' => true]; }
                    public function get_settings_fields(): array { return []; }
                    public function validate_settings(array $settings): array { return []; }
                    public function verify_connection(): array { return ['success' => true]; }
                PHP;

            file_put_contents(
                $dir . '/includes/channels/class-baseabstracta.php',
                "<?php\nnamespace ConvocaPublisher\\Channels;\nabstract class Baseabstracta implements ChannelInterface\n{\n"
                . str_replace('ID_AQUI', 'base', $canal)
                . "\n}\n"
            );

            file_put_contents(
                $dir . '/includes/channels/class-anonima.php',
                "<?php\nnamespace ConvocaPublisher\\Channels;\nreturn new class implements ChannelInterface\n{\n"
                . str_replace('ID_AQUI', 'anonima', $canal)
                . "\n};\n"
            );

            $code = 'define("ABSPATH", true);'
                . ' define("CONVOCA_PUBLISHER_PLUGIN_DIR", ' . var_export($dir . '/', true) . ');'
                . ' require ' . var_export($autoload, true) . ';'
                . ' $c = \ConvocaPublisher\Plugin::discover_channels();'
                . ' echo count($c) . ":" . implode(",", array_keys($c));';

            $out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1');

            foreach (glob($dir . '/includes/channels/*.php') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir . '/includes/channels');
            rmdir($dir . '/includes');
            rmdir($dir);

            $this->assertStringStartsWith('7:', $out, 'Solo deben entrar los canales de verdad: ' . $out);
            $this->assertStringNotContainsString('base', $out, 'Una base abstracta no se registra: ' . $out);
            $this->assertStringNotContainsString('anonima', $out, 'Una clase anónima no se registra: ' . $out);
        }
    }
}
