<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\PositionStatus;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Order;
use App\Models\Position;
use App\Models\Strategy;
use App\Models\StrategyRiskSettings;
use App\Models\User;
use App\Services\Bot\StrategyModeResolver;
use App\Services\Bot\TradePnlCalculator;
use App\Services\Bot\TradingLogger;
use App\Services\Bot\DailyPerformanceService;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Notifications\TradeTelegramNotifier;
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

    public function test_resolve_sell_quantity_caps_partial_exit_by_exchange_free_balance(): void
    {
        [$bot] = $this->makeBotGraph();

        $position = Position::factory()->create([
            'bot_id' => $bot->id,
            'symbol' => 'ETHUSDT',
            'quantity' => 2.0,
            'status' => PositionStatus::Open->value,
        ]);

        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getCoinFreeBalance')
            ->once()
            ->with(Mockery::type(ExchangeAccount::class), 'ETH')
            ->andReturn(0.3);
        $exchange->shouldReceive('normalizeQuantity')
            ->once()
            ->with(Mockery::type(ExchangeAccount::class), 'ETHUSDT', 0.3)
            ->andReturn(0.3);

        $result = $this->invokeResolveSellQuantity(
            $this->makeService($exchange),
            $position->fresh(['bot.exchangeAccount']),
            0.5,
            100.0,
        );

        $this->assertFalse($result['is_dust']);
        $this->assertEqualsWithDelta(0.3, $result['qty'], 0.001);
    }

    public function test_resolve_sell_quantity_uses_exchange_free_balance_for_full_exit(): void
    {
        [$bot] = $this->makeBotGraph();

        $position = Position::factory()->create([
            'bot_id' => $bot->id,
            'symbol' => 'ETHUSDT',
            'quantity' => 2.0,
            'status' => PositionStatus::Open->value,
        ]);

        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getCoinFreeBalance')
            ->once()
            ->with(Mockery::type(ExchangeAccount::class), 'ETH')
            ->andReturn(1.8);
        $exchange->shouldReceive('normalizeQuantity')
            ->once()
            ->with(Mockery::type(ExchangeAccount::class), 'ETHUSDT', 1.8)
            ->andReturn(1.8);

        $result = $this->invokeResolveSellQuantity(
            $this->makeService($exchange),
            $position->fresh(['bot.exchangeAccount']),
            1.0,
            100.0,
        );

        $this->assertFalse($result['is_dust']);
        $this->assertEqualsWithDelta(1.8, $result['qty'], 0.001);
    }

    /**
     * @return array{qty: float, is_dust: bool}
     */
    private function invokeResolveSellQuantity(
        PositionService $service,
        Position $position,
        float $portion,
        float $price,
    ): array {
        $method = new \ReflectionMethod(PositionService::class, 'resolveSellQuantity');
        $method->setAccessible(true);

        return $method->invoke($service, $position, $portion, $price);
    }

    private function makeBotGraph(): array
    {
        $user = User::factory()->create();
        $strategy = Strategy::query()->create([
            'name' => 'Test Strategy',
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

    private function makeService(?BybitExchangeService $exchange = null): PositionService
    {
        return new PositionService(
            $exchange ?? Mockery::mock(BybitExchangeService::class),
            Mockery::mock(TradingLogger::class),
            Mockery::mock(DailyPerformanceService::class),
            Mockery::mock(TechnicalIndicatorService::class),
            new TradePnlCalculator(),
            Mockery::mock(TradeTelegramNotifier::class),
            new StrategyModeResolver(),
        );
    }
}