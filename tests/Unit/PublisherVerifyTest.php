<?php

namespace ConvocaPublisher\Tests;

use PHPUnit\Framework\TestCase;

class PublisherVerifyTest extends TestCase
{
    public function testFacebookVerifyWithoutTokenReturnsError(): void
    {
        $channel = new \ConvocaPublisher\Channels\Facebook();
        $result = $channel->verify_connection();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
    }

    public function testTelegramVerifyWithoutTokenReturnsError(): void
    {
        $channel = new \ConvocaPublisher\Channels\Telegram();
        $result = $channel->verify_connection();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
    }

    public function testLinkedInVerifyWithoutTokenReturnsError(): void
    {
        $channel = new \ConvocaPublisher\Channels\Linkedin();
        $result = $channel->verify_connection();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
    }

    public function testTwitterVerifyWithoutTokenReturnsError(): void
    {
        $channel = new \ConvocaPublisher\Channels\Twitter();
        $result = $channel->verify_connection();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
    }

    public function testTikTokVerifyWithoutTokenReturnsError(): void
    {
        $channel = new \ConvocaPublisher\Channels\Tiktok();
        $result = $channel->verify_connection();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
    }

    public function testGMBVerifyWithoutTokenReturnsError(): void
    {
        $channel = new \ConvocaPublisher\Channels\Googlemybusiness();
        $result = $channel->verify_connection();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
    }

    public function testMastodonVerifyWithoutTokenReturnsError(): void
    {
        $channel = new \ConvocaPublisher\Channels\Mastodon();
        $result = $channel->verify_connection();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
    }
}
