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
    }
}
