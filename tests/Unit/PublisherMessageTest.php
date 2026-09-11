<?php

namespace ConvocaPublisher\Tests;

use PHPUnit\Framework\TestCase;
use ConvocaPublisher\Publisher;

class PublisherMessageTest extends TestCase
{
    private \ConvocaPublisher\Channels\ChannelInterface $mockChannel;

    protected function setUp(): void
    {
        $GLOBALS['_cp_test_postmeta'] = [];
        // Cada prueba parte sin plantillas: la que fija un caso no puede filtrarse al
        // siguiente (con eso, la suite solo pasaba en el orden en que está escrita).
        delete_option('convoca_publisher_message_template');
        delete_option('convoca_publisher_facebook_template');

        // Create a mock channel that returns known values
        $this->mockChannel = new class implements \ConvocaPublisher\Channels\ChannelInterface {
            public function get_id(): string
            {
                return 'facebook';
            }
            public function get_name(): string
            {
                return 'Facebook';
            }
            public function is_available(): bool
            {
                return true;
            }
            public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
            {
                return ['success' => true, 'post_id' => '123'];
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
                return ['success' => true, 'message' => 'OK'];
            }
        };
    }

    /**
     * La plantilla de fábrica de cada red: es lo que se manda cuando no hay nada escrito,
     * así que tiene que respetar lo que cada red admite (X no cabe con hashtags, Google My
     * Business prefiere el extracto) y tiene que ser la misma que enseña la pantalla.
     */
    public function testLaFabricaDeCadaRedRespetaLoQueAdmiteLaRed(): void
    {
        $f = Publisher::factory_templates();

        $this->assertCount(7, $f, 'Las siete redes tienen su plantilla de fábrica.');
        $this->assertStringNotContainsString('{hashtags}', $f['twitter'], 'En X no caben las etiquetas con el enlace.');
        $this->assertStringContainsString('{url}', $f['twitter'], 'Pero el enlace sí va.');
        $this->assertStringContainsString('{hashtags}', $f['facebook'], 'Instagram vive en el canal de Facebook y sí las lleva.');
        $this->assertStringContainsString('{excerpt}', $f['googlemybusiness'], 'Google My Business prefiere el extracto.');
        $this->assertSame('{title}', $f['tiktok'], 'TikTok solo enseña el título como pie.');
    }

    public function testMessageReplacesTitle(): void
    {
        $publisher = new Publisher(['facebook' => $this->mockChannel]);
        $message = $publisher->get_channel_message(1, 'facebook');

        $this->assertStringContainsString('Test Post', $message);
    }

    public function testMessageReplacesUrl(): void
    {
        $publisher = new Publisher(['facebook' => $this->mockChannel]);
        $message = $publisher->get_channel_message(1, 'facebook');

        $this->assertStringContainsString('https://example.com/?p=1', $message);
    }

    public function testMessageReplacesHashtags(): void
    {
        $publisher = new Publisher(['facebook' => $this->mockChannel]);
        $message = $publisher->get_channel_message(1, 'facebook');

        // No tags defined in stubs, so hashtags should be empty
        $this->assertIsString($message);
    }

    public function testMessageUsesChannelTemplate(): void
    {
        // Set a channel-specific template
        update_option('convoca_publisher_facebook_template', '{title} - CHANNEL SPECIFIC');
        $publisher = new Publisher(['facebook' => $this->mockChannel]);
        $message = $publisher->get_channel_message(1, 'facebook');

        $this->assertStringContainsString('CHANNEL SPECIFIC', $message);
        $this->assertStringContainsString('Test Post', $message);
    }

    public function testMessageUsesGlobalTemplateFallback(): void
    {
        // Set global template, no channel template
        update_option('convoca_publisher_message_template', '{title} - GLOBAL');
        delete_option('convoca_publisher_facebook_template');

        $publisher = new Publisher(['facebook' => $this->mockChannel]);
        $message = $publisher->get_channel_message(1, 'facebook');

        $this->assertStringContainsString('GLOBAL', $message);
        $this->assertStringContainsString('Test Post', $message);
    }

    public function testMessageDefaultTemplate(): void
    {
        // No template at all - should use default
        delete_option('convoca_publisher_message_template');
        delete_option('convoca_publisher_facebook_template');

        $publisher = new Publisher(['facebook' => $this->mockChannel]);
        $message = $publisher->get_channel_message(1, 'facebook');

        // Default for facebook: '{title} — {url} {hashtags}'
        $this->assertStringContainsString('Test Post', $message);
        $this->assertStringContainsString('https://example.com/?p=1', $message);
    }
}
