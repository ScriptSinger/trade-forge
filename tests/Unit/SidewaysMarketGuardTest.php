<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\BotStat;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\StrategyBtcTrendFilter;
use App\Models\StrategyRiskSettings;
use App\Models\User;
use App\Services\Bot\BtcTrendService;
use App\Services\Bot\DailyPerformanceService;
use App\Services\Bot\SidewaysMarketGuard;
use App\Services\Bot\TradingLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SidewaysMarketGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_does_not_block_when_daily_target_disabled(): void
    {
        $bot = $this->makeBot(dailyTargetEnabled: false);

        $guard = $this->makeGuard(btcBullish: false);

        $this->assertFalse($guard->blocksNewEntries($bot));
    }

    public function test_does_not_block_when_profit_target_not_reached(): void
    {
        $bot = $this->makeBot(profit: 10, startBalance: 1000, targetPct: 2.30);

        $guard = $this->makeGuard(btcBullish: false);

        $this->assertFalse($guard->blocksNewEntries($bot));
    }

    public function test_does_not_block_when_target_reached_but_btc_bullish(): void
    {
        $bot = $this->makeBot(profit: 25, startBalance: 1000, targetPct: 2.30);

        $guard = $this->makeGuard(btcBullish: true);

        $this->assertFalse($guard->blocksNewEntries($bot));
    }

    public function test_blocks_when_target_reached_in_sideways_market(): void
    {
        $bot = $this->makeBot(profit: 25, startBalance: 1000, targetPct: 2.30);

        $guard = $this->makeGuard(btcBullish: false);

        $this->assertTrue($guard->blocksNewEntries($bot));
    }

    public function test_does_not_block_without_start_balance(): void
    {
        $bot = $this->makeBot(profit: 25, startBalance: null, targetPct: 2.30);

        $guard = $this->makeGuard(btcBullish: false);

        $this->assertFalse($guard->blocksNewEntries($bot));
    }

    private function makeBot(
        bool $dailyTargetEnabled = true,
        float $targetPct = 2.30,
        float $profit = 0,
        ?float $startBalance = 1000,
    ): Bot {
        $user = User::factory()->create();
        $strategy = Strategy::query()->create([
            'name' => 'Test Strategy',
            'is_active' => true,
        ]);

        StrategyRiskSettings::query()->create([
            'strategy_id' => $strategy->id,
            'sl_multiplier' => 2.0,
            'tp_multiplier' => 3.0,
            'trailing_pct' => 1.5,
            'max_positions' => 3,
            'max_risk_per_trade' => 1.0,
            'daily_target_enabled' => $dailyTargetEnabled,
            'daily_profit_target_pct' => $targetPct,
        ]);

        StrategyBtcTrendFilter::query()->create([
            'strategy_id' => $strategy->id,
            'enabled' => true,
            'benchmark_symbol' => 'BTCUSDT',
            'benchmark_interval' => 60,
            'ema_fast' => 50,
            'ema_slow' => 200,
        ]);

        $exchangeAccount = ExchangeAccount::factory()->for($user)->create();

        $bot = Bot::factory()->for($user)->create([
            'exchange_account_id' => $exchangeAccount->id,
            'strategy_id' => $strategy->id,
        ]);

        BotStat::query()->create([
            'bot_id' => $bot->id,
            'date' => now()->timezone(config('app.timezone'))->toDateString(),
            'start_balance' => $startBalance,
            'start_balance_at' => $startBalance !== null ? now() : null,
            'profit' => $profit,
        ]);

        return $bot->fresh(['strategy.riskSettings', 'strategy.btcTrendFilter', 'exchangeAccount']);
    }

    private function makeGuard(bool $btcBullish): SidewaysMarketGuard
    {
        $performance = Mockery::mock(DailyPerformanceService::class);
        $performance->shouldReceive('startBalance')->andReturnUsing(
            fn (Bot $bot) => (float) ($bot->stats()->first()?->start_balance ?? 0)
        );
        $performance->shouldReceive('profitPct')->andReturnUsing(function (Bot $bot) {
            $stat = $bot->stats()->first();
            $start = (float) ($stat?->start_balance ?? 0);

            if ($start <= 0) {
                return 0.0;
            }

            return ((float) $stat->profit / $start) * 100;
        });

        $btcTrend = Mockery::mock(BtcTrendService::class);
        $btcTrend->shouldReceive('isBullish')->andReturn($btcBullish);

        $log = Mockery::mock(TradingLogger::class);
        $log->shouldReceive('riskInfo')->zeroOrMoreTimes();
        $log->shouldReceive('riskDebug')->zeroOrMoreTimes();

        return new SidewaysMarketGuard($performance, $btcTrend, $log);
    }
}