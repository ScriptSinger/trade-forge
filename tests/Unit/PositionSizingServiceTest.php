<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\StrategyType;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\StrategyRiskSettings;
use App\Models\User;
use App\Services\Bot\PositionSizingService;
use App\Services\Exchange\BybitExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PositionSizingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calculates_quantity_from_balance_risk_and_price_risk(): void
    {
        $bot = $this->makeBot(riskFraction: 0.02);

        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getUsdtWalletBalance')->once()->andReturn(1000.0);

        $service = new PositionSizingService($exchange);

        // risk = 20 USDT, price risk = 4 → size = 5 coins, cost = 500 (capped to 30% = 300)
        $qty = $service->calculateQuantity($bot, entryPrice: 100.0, stopLoss: 96.0);

        $this->assertEqualsWithDelta(3.0, $qty, 0.001);
    }

    public function test_returns_null_when_order_below_minimum_usdt(): void
    {
        $bot = $this->makeBot(riskFraction: 0.02);

        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getUsdtWalletBalance')->once()->andReturn(10.0);

        $service = new PositionSizingService($exchange);

        $this->assertNull($service->calculateQuantity($bot, entryPrice: 100.0, stopLoss: 99.0));
    }

    private function makeBot(float $riskFraction): Bot
    {
        $user = User::factory()->create();
        $strategy = Strategy::query()->create([
            'name' => 'Sizing Strategy',
            'type' => StrategyType::Hybrid->value,
            'is_active' => true,
        ]);

        StrategyRiskSettings::query()->create([
            'strategy_id' => $strategy->id,
            'sl_multiplier' => 2.0,
            'tp_multiplier' => 3.0,
            'trailing_pct' => 1.5,
            'max_positions' => 3,
            'max_risk_per_trade' => $riskFraction,
        ]);

        $exchangeAccount = ExchangeAccount::factory()->for($user)->create();

        return Bot::factory()->for($user)->create([
            'exchange_account_id' => $exchangeAccount->id,
            'strategy_id' => $strategy->id,
            'risk_per_trade' => $riskFraction,
        ])->fresh(['strategy.riskSettings', 'exchangeAccount']);
    }
}