<?php

/**
 * Enrutado por categoría y etiqueta.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Routing;
use PHPUnit\Framework\TestCase;

final class RoutingTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();
        parent::setUp();

        $GLOBALS['_cp_test_options']   = [];
        $GLOBALS['_cp_test_categories'] = [];
        $GLOBALS['_cp_test_tags']       = [];
    }

    public function test_sin_reglas_todas_las_cuentas(): void
    {
        $accounts = ['a' => (object) [], 'b' => (object) []];

        $this->assertSame($accounts, Routing::filter_accounts(5, $accounts));
    }

    public function test_una_categoria_manda_solo_a_sus_cuentas(): void
    {
        $GLOBALS['_cp_test_categories'][5] = [3];
        $GLOBALS['_cp_test_options'][Routing::OPTION] = ['cat_3' => ['b']];

        $accounts = ['a' => (object) [], 'b' => (object) []];

        $this->assertSame(['b'], array_keys(Routing::filter_accounts(5, $accounts)));
    }

    public function test_una_etiqueta_tambien_enruta(): void
    {
        $GLOBALS['_cp_test_tags'][5] = [9];
        $GLOBALS['_cp_test_options'][Routing::OPTION] = ['tag_9' => ['a']];

        $accounts = ['a' => (object) [], 'b' => (object) []];

        $this->assertSame(['a'], array_keys(Routing::filter_accounts(5, $accounts)));
    }

    public function test_sin_coincidencia_no_va_a_ninguna_cuenta(): void
    {
        $GLOBALS['_cp_test_categories'][5] = [3];
        $GLOBALS['_cp_test_options'][Routing::OPTION] = ['cat_99' => ['a']];

        $accounts = ['a' => (object) [], 'b' => (object) []];

        $this->assertSame([], Routing::filter_accounts(5, $accounts));
        $this->assertNotSame('', Routing::no_match_note(5));
    }

    public function test_las_reglas_invalidas_se_ignoran(): void
    {
        $GLOBALS['_cp_test_options'][Routing::OPTION] = [
            'cat_3' => ['a'],
            'no-es-una-clave' => ['b'],
            'tag_9' => [],
        ];
        $GLOBALS['_cp_test_categories'][5] = [3];

        $accounts = ['a' => (object) [], 'b' => (object) []];

        $this->assertSame(['a'], array_keys(Routing::filter_accounts(5, $accounts)));
        $this->assertSame(['cat_3' => ['a']], Routing::rules());
    }
}
