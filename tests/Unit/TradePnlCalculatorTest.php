<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Bot\TradePnlCalculator;
use Tests\TestCase;

class TradePnlCalculatorTest extends TestCase
{
    public function test_calculates_net_profit_with_spot_fees(): void
    {
        $calculator = new TradePnlCalculator;

        $result = $calculator->calculate(
            entryPrice: 100.0,
            exitPrice: 110.0,
            quantity: 1.0,
        );

        $this->assertEqualsWithDelta(9.79, $result['profit_loss'], 0.001);
        $this->assertEqualsWithDelta(0.21, $result['fees'], 0.001);
        $this->assertEqualsWithDelta(9.79, $result['profit_percent'], 0.01);
    }
}
