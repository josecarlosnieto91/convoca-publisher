<?php

/**
 * Saneadores de los ajustes nuevos.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Admin;
use ConvocaPublisher\Plugin;
use ConvocaPublisher\Profile_Store;
use PHPUnit\Framework\TestCase;

final class AdminAjustesTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();
        Plugin::reset();
        $GLOBALS['_cp_test_options'] = [];

        parent::setUp();
    }

    public function test_el_limite_diario_se_recorta(): void
    {
        $this->assertSame(0, Admin::sanitize_daily_limit(-3));
        $this->assertSame(20, Admin::sanitize_daily_limit(99));
        $this->assertSame(4, Admin::sanitize_daily_limit('4'));
    }

    public function test_el_enrutado_solo_guarda_cuentas_existentes(): void
    {
        $cuenta = Profile_Store::create('telegram', 'Telegram — Pruebas', [
            'convoca_publisher_telegram_token' => 'TOKEN',
        ]);
        $this->assertIsArray($cuenta);

        Plugin::reset();

        $rules = Admin::sanitize_routing([
            'cat_3' => [$cuenta['id'], 'no-existe'],
            'tag_9' => ['no-existe'],
            'invalida' => ['x'],
        ]);

        $this->assertSame(['cat_3' => [$cuenta['id']]], $rules);
    }
}
