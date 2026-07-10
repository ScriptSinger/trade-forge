<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\StrategyRiskSettings;
use App\Models\User;
use App\Services\Bot\Risk\PositionSizingService;
use App\Services\Bot\Risk\SizingResult;
use App\Services\Exchange\Balance\AccountBalanceSnapshot;
use App\Services\Exchange\Bybit\BybitExchangeService;
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

        $result = $service->calculateQuantity($bot, 'BTCUSDT', entryPrice: 100.0, stopLoss: 96.0);

        $this->assertTrue($result->ok());
        $this->assertEqualsWithDelta(3.0, $result->quantity, 0.001);
        $this->assertSame(SizingResult::REASON_OK, $result->reason);
    }

    public function test_caps_cost_by_free_usdt_balance(): void
    {
        $bot = $this->makeBot(riskFraction: 0.02);
        $exchange = $this->makeExchangeMock(total: 1000.0, free: 100.0, normalizedQty: 0.98);

        $service = new PositionSizingService($exchange);

        $result = $service->calculateQuantity($bot, 'BTCUSDT', entryPrice: 100.0, stopLoss: 96.0);

        $this->assertTrue($result->ok());
        $this->assertEqualsWithDelta(0.98, $result->quantity, 0.001);
        $this->assertSame('free_balance', $result->debug['capped_by'] ?? null);
    }

    public function test_rejects_with_below_min_order_when_cost_too_small(): void
    {
        $bot = $this->makeBot(riskFraction: 0.02);
        $exchange = $this->makeExchangeMock(total: 10.0, free: 10.0);

        $service = new PositionSizingService($exchange);

        $result = $service->calculateQuantity($bot, 'BTCUSDT', entryPrice: 100.0, stopLoss: 99.0);

        $this->assertFalse($result->ok());
        $this->assertSame(SizingResult::REASON_BELOW_MIN_ORDER, $result->reason);
        $this->assertArrayHasKey('final_cost_usdt', $result->debug);
        $this->assertArrayHasKey('wallet', $result->debug);
    }

    private function makeExchangeMock(float $total, float $free, ?float $normalizedQty = null): BybitExchangeService
    {
        $exchange = Mockery::mock(BybitExchangeService::class);
        $exchange->shouldReceive('getUsdtBalance')->once()->andReturn(new AccountBalanceSnapshot(
            coin: 'USDT',
            wallet: $total,
            free: $free,
            locked: max(0.0, $total - $free),
            freeSource: 'walletBalance_minus_locked',
        ));

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
