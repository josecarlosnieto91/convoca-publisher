<?php

/**
 * Límite diario por cuenta.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Queue;
use PHPUnit\Framework\TestCase;

final class QueueDailyLimitTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();
        parent::setUp();

        $GLOBALS['_cp_test_options'] = [];
        $GLOBALS['_cp_test_timezone'] = 'UTC';
    }

    public function test_sin_limite_nunca_bloquea(): void
    {
        $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'] = [
            ['channel' => 'telegram-1', 'success' => true, 'time' => current_time('mysql')],
        ];

        $this->assertFalse(Queue::daily_limit_reached('telegram-1'));
    }

    public function test_cuenta_las_publicaciones_correctas_de_hoy(): void
    {
        $hoy = current_time('Y-m-d') . ' 10:00:00';
        $GLOBALS['_cp_test_options']['convoca_publisher_daily_limit'] = 2;
        $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'] = [
            ['channel' => 'telegram-1', 'success' => true, 'time' => $hoy],
            ['channel' => 'telegram-1', 'success' => true, 'time' => $hoy],
        ];

        $this->assertTrue(Queue::daily_limit_reached('telegram-1'));
        $this->assertFalse(Queue::daily_limit_reached('telegram-2'));
    }

    public function test_las_pruebas_y_los_fallos_no_cuentan(): void
    {
        $hoy = current_time('Y-m-d') . ' 10:00:00';
        $GLOBALS['_cp_test_options']['convoca_publisher_daily_limit'] = 1;
        $GLOBALS['_cp_test_options']['convoca_publisher_publish_log'] = [
            ['channel' => 'telegram-1', 'success' => true, 'time' => $hoy, 'test' => true],
            ['channel' => 'telegram-1', 'success' => false, 'time' => $hoy],
        ];

        $this->assertFalse(Queue::daily_limit_reached('telegram-1'));
    }
}
