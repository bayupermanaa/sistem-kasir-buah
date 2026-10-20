<?php

namespace Tests\Unit;

use App\Support\DecimalMath;
use Tests\TestCase;

class DecimalMathTest extends TestCase
{
    public function test_quantity_and_money_round_trip_without_float_arithmetic(): void
    {
        $this->assertSame(6249, DecimalMath::parse('6.249', 3));
        $this->assertSame('6.249', DecimalMath::format(6249, 3));
        $this->assertSame(100000010, DecimalMath::parse('1000000.10', 2));
        $this->assertSame('1000000.10', DecimalMath::format(100000010, 2));
    }

    public function test_scaled_multiplication_uses_half_up_rounding(): void
    {
        $quantityMilli = DecimalMath::parse('0.300', 3);
        $priceCents = DecimalMath::parse('10000.10', 2);

        $this->assertSame('3000.03', DecimalMath::format(DecimalMath::multiply($quantityMilli, $priceCents, 1000), 2));
    }
}
