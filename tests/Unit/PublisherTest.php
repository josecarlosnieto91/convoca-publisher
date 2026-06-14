<?php

namespace ConvocaPublisher\Tests;

use PHPUnit\Framework\TestCase;

class PublisherTest extends TestCase
{
    public function testAllChannelsImplementInterface(): void
    {
        $channels = [
            new \ConvocaPublisher\Channels\Facebook(),
            new \ConvocaPublisher\Channels\Linkedin(),
            new \ConvocaPublisher\Channels\Twitter(),
            new \ConvocaPublisher\Channels\Tiktok(),
            new \ConvocaPublisher\Channels\Googlemybusiness(),
            new \ConvocaPublisher\Channels\Telegram(),
            new \ConvocaPublisher\Channels\Mastodon(),
        ];

        foreach ($channels as $channel) {
            $this->assertInstanceOf(\ConvocaPublisher\Channels\ChannelInterface::class, $channel);
            $this->assertIsString($channel->get_id());
            $this->assertIsString($channel->get_name());
            $this->assertIsBool($channel->is_available());
            $this->assertIsArray($channel->get_settings_fields());
            $this->assertIsArray($channel->validate_settings([]));
        }
    }

    public function testUnconfiguredChannelReturnsError(): void
    {
        $channel = new \ConvocaPublisher\Channels\Facebook();
        $result = $channel->publish(0, 'test', 'https://example.com');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testCryptoEncryptDecrypt(): void
    {
        $original = 'test_token_value_12345';

        $encrypted = \ConvocaPublisher\Crypto::encrypt($original);
        $this->assertNotEquals($original, $encrypted);

        $decrypted = \ConvocaPublisher\Crypto::decrypt($encrypted);
        $this->assertEquals($original, $decrypted);
    }

    public function testSchedulerCronSchedules(): void
    {
        $schedules = \ConvocaPublisher\Scheduler::add_cron_interval([]);
        $this->assertArrayHasKey('every_15min', $schedules);
        $this->assertEquals(900, $schedules['every_15min']['interval']);
    }

    public function testHashtagGeneration(): void
    {
        $tags = ['Ciencia Ciudadana', 'Restauración Ecológica', 'Bosque', 'Biodiversidad', 'Voluntariado'];
        $hashtags = array_map(function (string $tag): string {
            $tag = sanitize_title($tag);
            return '#' . str_replace(['-', '_', ' '], '', $tag);
        }, array_slice($tags, 0, 5));

        $result = implode(' ', $hashtags);
        $this->assertStringContainsString('#cienciaciudadana', $result);
        $this->assertStringContainsString('#bosque', $result);
        $this->assertStringContainsString('#biodiversidad', $result);
        $this->assertCount(5, explode(' ', $result));
    }
}
