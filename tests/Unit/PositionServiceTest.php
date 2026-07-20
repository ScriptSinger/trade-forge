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
use App\Models\Trade;
use App\Models\User;
use App\Services\Bot\Engine\TradingLogger;
use App\Services\Bot\Performance\DailyPerformanceService;
use App\Services\Bot\Performance\TradePnlCalculator;
use App\Services\Bot\Strategy\StrategyModeResolver;
use App\Services\Exchange\Balance\AccountBalanceSnapshot;
use App\Services\Exchange\Bybit\BybitExchangeService;
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
        $this->assertEqualsWithDelta(0.0, (float) $position->sold_quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $position->realized_pnl, 0.0001);
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
        $exchange->shouldReceive('getAccountBalance')
            ->once()
            ->with(Mockery::type(ExchangeAccount::class), 'ETH')
            ->andReturn(new AccountBalanceSnapshot('ETH', 0.3, 0.3, 0.0, 'availableBalance'));
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

    public function test_resolve_sell_quantity_full_exit_uses_stored_qty_not_extra_free_balance(): void
    {
        [$bot] = $this->makeBotGraph();

        $position = Position::factory()->create([
            'bot_id' => $bot->id,
            'symbol' => 'ETHUSDT',
            'quantity' => 1.0,
            'status' => PositionStatus::Open->value,
        ]);

        $exchange = Mockery::mock(BybitExchangeService::class);
        // Exchange free includes leftover bags (5.0) but position remainder is 1.0.
        $exchange->shouldReceive('getAccountBalance')
            ->once()
            ->with(Mockery::type(ExchangeAccount::class), 'ETH')
            ->andReturn(new AccountBalanceSnapshot('ETH', 5.0, 5.0, 0.0, 'availableBalance'));
        $exchange->shouldReceive('normalizeQuantity')
            ->once()
            ->with(Mockery::type(ExchangeAccount::class), 'ETHUSDT', 1.0)
            ->andReturn(1.0);

        $result = $this->invokeResolveSellQuantity(
            $this->makeService($exchange),
            $position->fresh(['bot.exchangeAccount']),
            1.0,
            100.0,
        );

        $this->assertFalse($result['is_dust']);
        $this->assertEqualsWithDelta(1.0, $result['qty'], 0.001);
    }

    public function test_resolve_sell_quantity_full_exit_caps_by_exchange_when_free_is_lower(): void
    {
        [$bot] = $this->makeBotGraph();

        $position = Position::factory()->create([
            'bot_id' => $bot->id,
            'symbol' => 'ETHUSDT',
            'quantity' => 2.0,
            'status' => PositionStatus::Open->value,
        ]);

        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getAccountBalance')
            ->once()
            ->with(Mockery::type(ExchangeAccount::class), 'ETH')
            ->andReturn(new AccountBalanceSnapshot('ETH', 1.8, 1.8, 0.0, 'availableBalance'));
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

    public function test_partial_exit_does_not_mutate_position_when_exchange_rejects(): void
    {
        [$bot] = $this->makeBotGraph();

        $position = Position::factory()->create([
            'bot_id' => $bot->id,
            'symbol' => 'ETHUSDT',
            'entry_price' => 100.0,
            'quantity' => 2.0,
            'tp' => 110.0,
            'status' => PositionStatus::Open->value,
        ]);

        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getAccountBalance')
            ->once()
            ->andReturn(new AccountBalanceSnapshot('ETH', 2.0, 2.0, 0.0, 'availableBalance'));
        $exchange->shouldReceive('normalizeQuantity')
            ->once()
            ->andReturn(1.0);
        $exchange->shouldReceive('placeMarketOrder')
            ->once()
            ->andReturn(['retCode' => 10001, 'retMsg' => 'Insufficient balance', 'result' => []]);

        $daily = Mockery::mock(DailyPerformanceService::class);
        $daily->shouldNotReceive('recordPartialPnl');

        $logger = Mockery::mock(TradingLogger::class);
        $logger->shouldReceive('orderInfo')->once();
        $logger->shouldReceive('orderError')->once();

        $service = $this->makeService($exchange, $daily, $logger);
        $ok = $this->invokeExecutePartialExit(
            $service,
            $position->fresh(['bot.strategy.riskSettings', 'bot.exchangeAccount']),
            110.0,
            0.5,
            'Take Profit (50%)',
            'Hybrid',
        );

        $this->assertFalse($ok);

        $position->refresh();
        $this->assertEqualsWithDelta(2.0, (float) $position->quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $position->sold_quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $position->realized_pnl, 0.0001);
        $this->assertSame(PositionStatus::Open, $position->status);
    }

    public function test_hybrid_full_exit_combines_partial_and_remainder_into_one_trade(): void
    {
        [$bot] = $this->makeBotGraph();

        $position = Position::factory()->create([
            'bot_id' => $bot->id,
            'symbol' => 'ETHUSDT',
            'entry_price' => 100.0,
            'quantity' => 1.0,
            'sold_quantity' => 1.0,
            'realized_pnl' => 0.8,
            'realized_fees' => 0.2,
            'realized_exit_value' => 110.0,
            'half_sold' => true,
            'status' => PositionStatus::Open->value,
            'opened_at' => now()->subHour(),
        ]);

        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getAccountBalance')
            ->once()
            ->andReturn(new AccountBalanceSnapshot('ETH', 1.0, 1.0, 0.0, 'availableBalance'));
        $exchange->shouldReceive('normalizeQuantity')
            ->once()
            ->andReturn(1.0);
        $exchange->shouldReceive('placeMarketOrder')
            ->once()
            ->andReturn(['retCode' => 0, 'retMsg' => 'OK', 'result' => ['orderId' => '1']]);

        $daily = Mockery::mock(DailyPerformanceService::class);
        $daily->shouldReceive('recordClosedTrade')
            ->once()
            ->with(
                Mockery::type(Trade::class),
                0.8,
                0.2,
            );

        $logger = Mockery::mock(TradingLogger::class);
        $logger->shouldReceive('orderInfo')->once();
        $logger->shouldReceive('auditPositionExit')->once();

        $telegram = Mockery::mock(TradeTelegramNotifier::class);
        $telegram->shouldReceive('notifyExit')->once();

        $service = $this->makeService($exchange, $daily, $logger, $telegram);
        $this->invokeExecuteExit(
            $service,
            $position->fresh(['bot.strategy.riskSettings', 'bot.exchangeAccount']),
            105.0,
            'SL/Trailing (remainder)',
            'Hybrid',
        );

        $position->refresh();
        $this->assertSame(PositionStatus::Closed, $position->status);

        $trade = Trade::query()->firstOrFail();
        $this->assertEqualsWithDelta(2.0, (float) $trade->quantity, 0.0001);
        // exit value: 110 + 105 = 215, avg exit 107.5
        $this->assertEqualsWithDelta(107.5, (float) $trade->exit_price, 0.001);
        // remainder leg: entry 100 * 1, exit 105, fee 0.001* (100+105)=0.205
        // net leg = 5 - 0.205 = 4.795; total pnl = 0.8 + 4.795
        $this->assertEqualsWithDelta(0.8 + 4.795, (float) $trade->profit_loss, 0.001);
        $this->assertEqualsWithDelta(0.2 + 0.205, (float) $trade->fees, 0.001);
    }

    public function test_full_exit_does_not_close_when_exchange_rejects(): void
    {
        [$bot] = $this->makeBotGraph();

        $position = Position::factory()->create([
            'bot_id' => $bot->id,
            'symbol' => 'ETHUSDT',
            'entry_price' => 100.0,
            'quantity' => 1.0,
            'status' => PositionStatus::Open->value,
        ]);

        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getAccountBalance')
            ->once()
            ->andReturn(new AccountBalanceSnapshot('ETH', 1.0, 1.0, 0.0, 'availableBalance'));
        $exchange->shouldReceive('normalizeQuantity')->once()->andReturn(1.0);
        $exchange->shouldReceive('placeMarketOrder')
            ->once()
            ->andReturn(['retCode' => 170131, 'retMsg' => 'Insufficient balance', 'result' => []]);

        $daily = Mockery::mock(DailyPerformanceService::class);
        $daily->shouldNotReceive('recordClosedTrade');

        $logger = Mockery::mock(TradingLogger::class);
        $logger->shouldReceive('orderInfo')->once();
        $logger->shouldReceive('orderError')->once();

        $service = $this->makeService($exchange, $daily, $logger);
        $this->invokeExecuteExit(
            $service,
            $position->fresh(['bot.strategy.riskSettings', 'bot.exchangeAccount']),
            95.0,
            'Stop Loss',
            'Sniper',
        );

        $position->refresh();
        $this->assertSame(PositionStatus::Open, $position->status);
        $this->assertSame(0, Trade::query()->count());
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

    private function invokeExecutePartialExit(
        PositionService $service,
        Position $position,
        float $price,
        float $portion,
        string $reason,
        string $runtimeMode,
    ): bool {
        $method = new \ReflectionMethod(PositionService::class, 'executePartialExit');
        $method->setAccessible(true);

        return $method->invoke($service, $position, $price, $portion, $reason, $runtimeMode);
    }

    private function invokeExecuteExit(
        PositionService $service,
        Position $position,
        float $price,
        string $reason,
        string $runtimeMode,
    ): void {
        $method = new \ReflectionMethod(PositionService::class, 'executeExit');
        $method->setAccessible(true);
        $method->invoke($service, $position, $price, $reason, $runtimeMode);
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
            'spot_fee_rate' => 0.001,
        ]);

        $exchangeAccount = ExchangeAccount::factory()->for($user)->create();

        $bot = Bot::factory()->for($user)->create([
            'exchange_account_id' => $exchangeAccount->id,
            'strategy_id' => $strategy->id,
        ]);

        return [$bot->fresh(['strategy.riskSettings'])];
    }

    private function makeService(
        ?BybitExchangeService $exchange = null,
        ?DailyPerformanceService $daily = null,
        ?TradingLogger $logger = null,
        ?TradeTelegramNotifier $telegram = null,
    ): PositionService {
        return new PositionService(
            $exchange ?? Mockery::mock(BybitExchangeService::class),
            $logger ?? Mockery::mock(TradingLogger::class),
            $daily ?? Mockery::mock(DailyPerformanceService::class),
            Mockery::mock(TechnicalIndicatorService::class),
            new TradePnlCalculator,
            $telegram ?? Mockery::mock(TradeTelegramNotifier::class),
            new StrategyModeResolver,
        );
    }
}
