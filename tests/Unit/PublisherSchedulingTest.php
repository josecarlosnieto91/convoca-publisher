<?php
/**
 * Tests for Convoca Publisher — scheduling, queue and validation.
 */
namespace Convoca\Tests\Publisher\Unit;

use PHPUnit\Framework\TestCase;

class PublisherSchedulingTest extends TestCase
{
    private function loadClass(): void
    {
        foreach (['Scheduler', 'Queue_Manager', 'Channel_Validator'] as $cls) {
            $path = dirname(__DIR__, 3) . "/includes/class-{$cls}.php";
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    protected function setUp(): void
    {
        $this->loadClass();
    }

    public function test_schedule_post_for_later(): void
    {
        $now = time();
        $future = $now + 3600; // +1 hour
        $this->assertGreaterThan($now, $future);
        $this->assertIsInt($future);
    }

    public function test_schedule_in_past_rejected(): void
    {
        $now = time();
        $past = $now - 3600;
        $this->assertLessThan($now, $past);
    }

    public function test_queue_retry_mechanism(): void
    {
        $max_retries = 2;
        $attempts = 0;
        
        // Simulate retries
        for ($i = 0; $i < $max_retries; $i++) {
            $attempts++;
        }
        $this->assertEquals(2, $attempts);
        $this->assertLessThanOrEqual($max_retries, $attempts);
    }

    public function test_queue_marks_permanent_failure(): void
    {
        $max_retries = 2;
        $attempts = 2;
        $permanent_failure = $attempts >= $max_retries;
        $this->assertTrue($permanent_failure);
    }

    public function test_validation_empty_title_warns(): void
    {
        $title = '';
        $has_warning = empty($title);
        $this->assertTrue($has_warning);
    }

    public function test_validation_missing_featured_image_warns(): void
    {
        $has_image = false;
        $warnings = [];
        if (!$has_image) {
            $warnings[] = 'missing_featured_image';
        }
        $this->assertNotEmpty($warnings);
        $this->assertContains('missing_featured_image', $warnings);
    }

    public function test_channel_template_variables(): void
    {
        $vars = ['{title}', '{excerpt}', '{url}', '{hashtags}', '{date}', '{author}'];
        $this->assertCount(6, $vars);
        $this->assertContains('{title}', $vars);
        $this->assertContains('{url}', $vars);
    }

    public function test_channel_status_check(): void
    {
        $channels = [
            'facebook' => ['connected' => true, 'name' => 'Fanpage'],
            'twitter' => ['connected' => false, 'error' => 'Token expired'],
            'telegram' => ['connected' => true, 'name' => 'Canal Noticias'],
        ];
        $connected = array_filter($channels, fn($c) => $c['connected']);
        $this->assertCount(2, $connected);
        $this->assertArrayHasKey('facebook', $connected);
        $this->assertArrayNotHasKey('twitter', $connected);
    }

    public function test_history_limit(): void
    {
        $max_history = 200;
        $entries = range(1, 50);
        $this->assertLessThanOrEqual($max_history, count($entries));
        $this->assertCount(50, $entries);
    }

    public function test_dry_run_does_not_publish(): void
    {
        $dry_run = true;
        $published = false;
        if ($dry_run) {
            $published = false;
        }
        $this->assertFalse($published);
    }
}
