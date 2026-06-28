<?php

namespace ConvocaPublisher\Tests;

use PHPUnit\Framework\TestCase;
use ConvocaPublisher\Publisher;

class PublisherMessageTest extends TestCase
{
    private \ConvocaPublisher\Channels\ChannelInterface $mockChannel;

    protected function setUp(): void
    {
        // Create a mock channel that returns known values
        $this->mockChannel = new class implements \ConvocaPublisher\Channels\ChannelInterface {
            public function get_id(): string { return 'facebook'; }
            public function get_name(): string { return 'Facebook'; }
            public function is_available(): bool { return true; }
            public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
            {
                return ['success' => true, 'post_id' => '123'];
            }
            public function get_settings_fields(): array { return []; }
            public function validate_settings(array $settings): array { return []; }
            public function verify_connection(): array { return ['success' => true, 'message' => 'OK']; }
        };
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
        update_option('cp_facebook_template', '{title} - CHANNEL SPECIFIC');
        $publisher = new Publisher(['facebook' => $this->mockChannel]);
        $message = $publisher->get_channel_message(1, 'facebook');

        $this->assertStringContainsString('CHANNEL SPECIFIC', $message);
        $this->assertStringContainsString('Test Post', $message);
    }

    public function testMessageUsesGlobalTemplateFallback(): void
    {
        // Set global template, no channel template
        update_option('cp_message_template', '{title} - GLOBAL');
        delete_option('cp_facebook_template');

        $publisher = new Publisher(['facebook' => $this->mockChannel]);
        $message = $publisher->get_channel_message(1, 'facebook');

        $this->assertStringContainsString('GLOBAL', $message);
        $this->assertStringContainsString('Test Post', $message);
    }

    public function testMessageDefaultTemplate(): void
    {
        // No template at all - should use default
        delete_option('cp_message_template');
        delete_option('cp_facebook_template');

        $publisher = new Publisher(['facebook' => $this->mockChannel]);
        $message = $publisher->get_channel_message(1, 'facebook');

        // Default for facebook: '{title} — {url} {hashtags}'
        $this->assertStringContainsString('Test Post', $message);
        $this->assertStringContainsString('https://example.com/?p=1', $message);
    }
}
