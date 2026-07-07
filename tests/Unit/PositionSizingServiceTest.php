<?php

declare(strict_types=1);

namespace Tests\Unit;

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
        $exchange = $this->makeExchangeMock(total: 1000.0, free: 1000.0, normalizedQty: 3.0);

        $service = new PositionSizingService($exchange);

        $qty = $service->calculateQuantity($bot, 'BTCUSDT', entryPrice: 100.0, stopLoss: 96.0);

        $this->assertEqualsWithDelta(3.0, $qty, 0.001);
    }

    public function test_caps_cost_by_free_usdt_balance(): void
    {
        $bot = $this->makeBot(riskFraction: 0.02);
        $exchange = $this->makeExchangeMock(total: 1000.0, free: 100.0, normalizedQty: 0.98);

        $service = new PositionSizingService($exchange);

        $qty = $service->calculateQuantity($bot, 'BTCUSDT', entryPrice: 100.0, stopLoss: 96.0);

        $this->assertEqualsWithDelta(0.98, $qty, 0.001);
    }

    public function test_returns_null_when_order_below_minimum_usdt(): void
    {
        $bot = $this->makeBot(riskFraction: 0.02);
        $exchange = $this->makeExchangeMock(total: 10.0, free: 10.0);

        $service = new PositionSizingService($exchange);

        $this->assertNull($service->calculateQuantity($bot, 'BTCUSDT', entryPrice: 100.0, stopLoss: 99.0));
    }

    private function makeExchangeMock(float $total, float $free, ?float $normalizedQty = null): BybitExchangeService
    {
        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getUsdtWalletBalance')->once()->andReturn($total);
        $exchange->shouldReceive('getUsdtFreeBalance')->once()->andReturn($free);

        if ($normalizedQty !== null) {
            $exchange->shouldReceive('normalizeQuantity')
                ->once()
                ->andReturn($normalizedQty);
        } else {
            $exchange->shouldReceive('normalizeQuantity')->never();
        }

        return $exchange;
    }

    private function makeBot(float $riskFraction): Bot
    {
        $user = User::factory()->create();
        $strategy = Strategy::query()->create([
            'name' => 'Sizing Strategy',
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
        ])->fresh(['strategy.riskSettings', 'exchangeAccount']);
    }
}
