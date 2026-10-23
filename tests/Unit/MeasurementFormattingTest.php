<?php

namespace Tests\Unit;

use Tests\TestCase;

class MeasurementFormattingTest extends TestCase
{
    public function test_quantity_and_weight_display_use_the_expected_precision(): void
    {
        $this->assertSame('2 · 16.00 kg', format_quantity_weight('2.00', '16'));
        $this->assertSame('2', format_quantity_weight('2.00', null));
        $this->assertSame('16.00 kg', format_quantity_weight(null, '16'));
    }
}
