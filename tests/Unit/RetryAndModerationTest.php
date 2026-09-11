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
        // El wpdb falso de una prueba no puede filtrarse a las siguientes: sin esto, la
        // suite solo pasa en el orden en que está escrita.
        $GLOBALS['wpdb'] = new \wpdb();
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

    // ── QA2 — Claim atómico en process_queue ────────────────────────────

    /**
     * Mock stateful de wpdb: filas en la cola, transición de estado
     * pending→processing solo si sigue pending (devuelve 0 si otro ya
     * reclamó), y registro de updates.
     */
    private function makeQueueWpdb(array $rows): object
    {
        $mock = new class ($rows) {
            public array $rows;
            public array $updates = [];
            public string $prefix = 'wp_';
            public int $insert_id = 0;

            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            public function prepare(string $q, mixed ...$a): string
            {
                return $q;
            }

            public function get_results(string $q = null, string $o = 'OBJECT'): array
            {
                // El código real compara con current_time('mysql') — hora local.
                // strtotime interpreta next_attempt como local, así que comparamos
                // contra el mismo instante en formato local.
                $now_local = date('Y-m-d H:i:s');
                return array_values(array_filter($this->rows, function ($r) use ($now_local) {
                    $next = strtotime((string) ($r->next_attempt ?? ''));
                    if ($r->status === 'pending') {
                        return $next !== false && $next <= strtotime($now_local);
                    }
                    if ($r->status === 'processing') {
                        $last = strtotime((string) ($r->last_attempt ?? ''));
                        return $last !== false && $last < strtotime($now_local) - 2 * 3600; // huérfano >2h
                    }
                    return false;
                }));
            }

            public function update(string $t, array $data, array $where): int
            {
                $this->updates[] = ['data' => $data, 'where' => $where];
                foreach ($this->rows as $i => $r) {
                    if ((int) $r->id === (int) $where['id']) {
                        // Solo reclamable si sigue en el estado esperado.
                        if (isset($where['status']) && $r->status !== $where['status']) {
                            return 0;
                        }
                        foreach ($data as $k => $v) {
                            $this->rows[$i]->$k = $v;
                        }
                        return 1;
                    }
                }
                return 0;
            }

            public function delete(string $t, array $where): int
            {
                foreach ($this->rows as $i => $r) {
                    if ((int) $r->id === (int) $where['id']) {
                        unset($this->rows[$i]);
                        return 1;
                    }
                }
                return 0;
            }

            public function get_charset_collate(): string
            {
                return '';
            }
        };

        return $mock;
    }

    public function testConcurrentProcessQueuePublishesOnce(): void
    {
        // Fila pending lista para reintentar (next_attempt en el pasado).
        $row = (object) [
            'id'          => 1,
            'post_id'     => 42,
            'channel'     => 'telegram',
            'payload'     => 'hola',
            'attempts'    => 0,
            'status'      => 'pending',
            'last_attempt' => null,
            'next_attempt' => date('Y-m-d H:i:s', time() - 10), // hora local, como current_time('mysql')
        ];

        $wpdb = $this->makeQueueWpdb([$row]);
        $GLOBALS['wpdb'] = $wpdb;

        $calls = 0;
        $channel = $this->makeChannel('telegram', static function () use (&$calls): array {
            $calls++;
            return ['success' => true, 'post_id' => 'x'];
        });

        // Stub global convoca_publisher(): devuelve un plugin mock con get_channel().
        // La función se define en el namespace global (PHP cae a global al resolver).
        if (!function_exists('convoca_publisher')) {
            eval('namespace { function convoca_publisher() { return $GLOBALS["_cp_publisher_stub"]; } }');
        }
        $GLOBALS['_cp_publisher_stub'] = new class ($channel) {
            private $channel;

            public function __construct($channel)
            {
                $this->channel = $channel;
            }

            public function get_channel(string $id)
            {
                return $id === 'telegram' ? $this->channel : null;
            }
        };

        // Simular dos crons solapados: ambos ven la misma fila pending.
        Retry::process_queue();
        Retry::process_queue();

        $this->assertSame(1, $calls, 'La publicación debe ocurrir una sola vez aunque dos crons se solapen.');

        // El primer update debe ser el claim atómico pending→processing.
        $first = $wpdb->updates[0] ?? null;
        $this->assertNotNull($first);
        $this->assertSame('processing', $first['data']['status'] ?? null);
        $this->assertSame('pending', $first['where']['status'] ?? null);
    }
}
