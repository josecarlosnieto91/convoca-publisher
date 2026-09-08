<?php

/**
 * Tests for Convoca Publisher — cola de reintentos (D14) y moderación previa (D15).
 */

namespace ConvocaPublisher\Tests;

use PHPUnit\Framework\TestCase;
use ConvocaPublisher\Publisher;
use ConvocaPublisher\Retry;

class RetryAndModerationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_cp_test_options'] = [];
        $GLOBALS['_cp_test_db'] = ['rows' => [], 'inserts' => []];
        $GLOBALS['wpdb']->insert_id = 0;
    }

    /**
     * Construir un canal mock con publish controlado.
     *
     * @param callable|null $onPublish
     */
    private function makeChannel(string $id, ?callable $onPublish = null): \ConvocaPublisher\Channels\ChannelInterface
    {
        $onPublish ??= static function (): array {
            return ['success' => true, 'post_id' => 'x'];
        };

        return new class ($id, $onPublish) implements \ConvocaPublisher\Channels\ChannelInterface {
            private string $id;

            /** @var callable */
            private $onPublish;

            public function __construct(string $id, callable $onPublish)
            {
                $this->id = $id;
                $this->onPublish = $onPublish;
            }

            public function get_id(): string
            {
                return $this->id;
            }

            public function get_name(): string
            {
                return ucfirst($this->id);
            }

            public function is_available(): bool
            {
                return true;
            }

            public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
            {
                return ($this->onPublish)($post_id, $message, $url, $image_url);
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

    // ── D14 — Backoff ──────────────────────────────────────────────

    public function testBackoffReturnsDefaultHours(): void
    {
        $this->assertSame([1, 4, 12, 24, 72], Retry::get_backoff());
    }

    public function testFirstBackoffIsOneHour(): void
    {
        $this->assertSame(3600, Retry::get_next_attempt_time(0, 0));
    }

    public function testSecondBackoffIsFourHours(): void
    {
        $this->assertSame(14400, Retry::get_next_attempt_time(1, 0));
    }

    public function testLastBackoffIsSeventyTwoHours(): void
    {
        $this->assertSame(259200, Retry::get_next_attempt_time(4, 0));
    }

    public function testBackoffClampsBeyondLast(): void
    {
        $this->assertSame(259200, Retry::get_next_attempt_time(99, 0));
    }

    public function testBackoffRespectsBaseTimestamp(): void
    {
        $this->assertSame(4600, Retry::get_next_attempt_time(0, 1000));
    }

    public function testEnqueueInsertsPendingRow(): void
    {
        $id = Retry::enqueue(5, 'telegram', 'hola', 0);

        $this->assertSame(1, $id);
        $inserts = $GLOBALS['_cp_test_db']['inserts'];
        $this->assertCount(1, $inserts);
        $this->assertSame(5, $inserts[0]['data']['post_id']);
        $this->assertSame('telegram', $inserts[0]['data']['channel']);
        $this->assertSame('hola', $inserts[0]['data']['payload']);
        $this->assertSame(0, $inserts[0]['data']['attempts']);
        $this->assertSame('pending', $inserts[0]['data']['status']);
        $this->assertNotEmpty($inserts[0]['data']['next_attempt']);
    }

    public function testEnqueueReviewInsertsPendingReviewRow(): void
    {
        Retry::enqueue_review(7, 'facebook', 'msg');

        $inserts = $GLOBALS['_cp_test_db']['inserts'];
        $this->assertCount(1, $inserts);
        $this->assertSame(7, $inserts[0]['data']['post_id']);
        $this->assertSame('pending_review', $inserts[0]['data']['status']);
        $this->assertSame(0, $inserts[0]['data']['attempts']);
    }

    // ── D15 — Moderación previa ────────────────────────────────────

    public function testModerationDefaultIsOff(): void
    {
        $publisher = new Publisher([]);

        $this->assertSame('off', $publisher->moderation_mode());
        $this->assertFalse($publisher->channel_needs_moderation('facebook'));
    }

    public function testModerationAllRequiresReview(): void
    {
        update_option('convoca_publisher_moderation', 'all');
        $publisher = new Publisher([]);

        $this->assertSame('all', $publisher->moderation_mode());
        $this->assertTrue($publisher->channel_needs_moderation('facebook'));
        $this->assertTrue($publisher->channel_needs_moderation('telegram'));
    }

    public function testModerationCanalRequiresReviewOnlyForSelected(): void
    {
        update_option('convoca_publisher_moderation', 'canal');
        update_option('convoca_publisher_moderation_channels', ['facebook']);
        $publisher = new Publisher([]);

        $this->assertTrue($publisher->channel_needs_moderation('facebook'));
        $this->assertFalse($publisher->channel_needs_moderation('telegram'));
    }

    public function testModerationCanalWithoutSelectionRequiresNothing(): void
    {
        update_option('convoca_publisher_moderation', 'canal');
        update_option('convoca_publisher_moderation_channels', []);
        $publisher = new Publisher([]);

        $this->assertFalse($publisher->channel_needs_moderation('facebook'));
    }

    public function testPendingReviewDoesNotPublish(): void
    {
        update_option('convoca_publisher_moderation', 'all');

        $calls = 0;
        $channel = $this->makeChannel('facebook', static function () use (&$calls): array {
            $calls++;
            return ['success' => true, 'post_id' => 'x'];
        });

        $publisher = new Publisher(['facebook' => $channel]);
        $results = $publisher->publish_post(1);

        $this->assertSame(0, $calls, 'No debe enviarse si está pendiente de moderación.');
        $this->assertArrayHasKey('facebook', $results);
        $this->assertTrue($results['facebook']['pending_review'] ?? false);

        $inserts = $GLOBALS['_cp_test_db']['inserts'];
        $this->assertNotEmpty($inserts);
        $this->assertSame('pending_review', end($inserts)['data']['status']);
    }

    public function testApprovedPublishes(): void
    {
        update_option('convoca_publisher_moderation', 'all');

        $calls = 0;
        $channel = $this->makeChannel('facebook', static function () use (&$calls): array {
            $calls++;
            return ['success' => true, 'post_id' => 'x'];
        });

        $publisher = new Publisher(['facebook' => $channel]);
        $results = $publisher->publish_post(1, false, true);

        $this->assertSame(1, $calls, 'Al aprobar debe enviarse.');
        $this->assertArrayHasKey('facebook', $results);
        $this->assertTrue($results['facebook']['success']);

        // Éxito => ni revisión ni reintento encolados.
        $this->assertSame([], $GLOBALS['_cp_test_db']['inserts']);
    }

    public function testFailedPublishEnqueuesRetry(): void
    {
        $calls = 0;
        $channel = $this->makeChannel('telegram', static function () use (&$calls): array {
            $calls++;
            return ['success' => false, 'error' => 'API down'];
        });

        $publisher = new Publisher(['telegram' => $channel]);
        $results = $publisher->publish_post(1);

        $this->assertSame(1, $calls);
        $this->assertFalse($results['telegram']['success']);

        $inserts = $GLOBALS['_cp_test_db']['inserts'];
        $this->assertNotEmpty($inserts);
        $this->assertSame('pending', end($inserts)['data']['status']);
        $this->assertSame('telegram', end($inserts)['data']['channel']);
    }
}
