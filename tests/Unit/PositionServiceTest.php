<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\PositionStatus;
use App\Enums\StrategyType;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Order;
use App\Models\Position;
use App\Models\Strategy;
use App\Models\StrategyRiskSettings;
use App\Models\User;
use App\Services\Bot\TradePnlCalculator;
use App\Services\Bot\TradingLogger;
use App\Services\Bot\DailyPerformanceService;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Position\PositionService;
use App\Services\Strategy\TechnicalIndicatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PositionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_from_order_persists_mode_sl_and_tp_from_normalized_flow(): void
    {
        [$bot] = $this->makeBotGraph();

        $order = Order::factory()->create([
            'bot_id' => $bot->id,
            'exchange_account_id' => $bot->exchange_account_id,
            'symbol' => 'ETHUSDT',
            'side' => OrderSide::Buy->value,
            'status' => OrderStatus::Filled->value,
            'price' => '2500.00000000',
            'quantity' => '0.50000000',
        ]);

        $service = $this->makeService();
        $service->syncFromOrder(
            bot: $bot,
            order: $order,
            signal: 'buy',
            mode: 'Hybrid',
            sl: 2400.0,
            tp: 2700.0,
        );

        $position = Position::query()->firstOrFail();

        $this->assertSame('Hybrid', $position->mode);
        $this->assertEqualsWithDelta(2400.0, (float) $position->sl, 0.001);
        $this->assertEqualsWithDelta(2700.0, (float) $position->tp, 0.001);
        $this->assertSame(PositionStatus::Open, $position->status);
    }

    private function makeBotGraph(): array
    {
        $user = User::factory()->create();
        $strategy = Strategy::query()->create([
            'name' => 'Test Strategy',
            'type' => StrategyType::Trend->value,
            'is_active' => true,
        ]);

        StrategyRiskSettings::query()->create([
            'strategy_id' => $strategy->id,
            'sl_multiplier' => 2.0,
            'tp_multiplier' => 3.0,
            'trailing_pct' => 2.0,
            'max_positions' => 3,
                'max_risk_per_trade' => 0.02,
        ]);

        $exchangeAccount = ExchangeAccount::factory()->for($user)->create();

        $bot = Bot::factory()->for($user)->create([
            'exchange_account_id' => $exchangeAccount->id,
            'strategy_id' => $strategy->id,
        ]);

        return [$bot->fresh(['strategy.riskSettings'])];
    }

    private function makeService(): PositionService
    {
        return new PositionService(
            Mockery::mock(BybitExchangeService::class),
            Mockery::mock(TradingLogger::class),
            Mockery::mock(DailyPerformanceService::class),
            Mockery::mock(TechnicalIndicatorService::class),
            new TradePnlCalculator(),
        );
    }
}