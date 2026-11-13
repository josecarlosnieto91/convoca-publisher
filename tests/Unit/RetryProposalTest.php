<?php

/**
 * Consultas de propuestas en la cola.
 *
 * @package ConvocaPublisher\Tests
 */

namespace ConvocaPublisher\Tests\Unit;

use ConvocaPublisher\Retry;
use PHPUnit\Framework\TestCase;

final class RetryProposalTest extends TestCase
{
    protected function setUp(): void
    {
        \cp_test_reset();
        parent::setUp();
    }

    public function test_has_proposal_consulta_la_tabla(): void
    {
        $GLOBALS['_cp_test_db']['var'] = '3';

        $this->assertTrue(Retry::has_proposal(5, 'telegram-1'));
    }

    public function test_las_propuestas_se_listan(): void
    {
        $GLOBALS['_cp_test_db']['results'] = [
            (object) ['id' => 1, 'post_id' => 5, 'channel' => 'telegram-1', 'payload' => 'Hola'],
        ];

        $items = Retry::get_proposals();

        $this->assertCount(1, $items);
        $this->assertSame('telegram-1', $items[0]->channel);
    }
}
