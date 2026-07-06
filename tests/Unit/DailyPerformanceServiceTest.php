<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\BotStat;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bot\DailyPerformanceService;
use App\Services\Bot\TradingLogger;
use App\Services\Exchange\BybitExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DailyPerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_profit_pct_is_relative_to_start_balance(): void
    {
        $bot = $this->makeBot();

        BotStat::query()->create([
            'bot_id' => $bot->id,
            'date' => now()->timezone(config('app.timezone'))->toDateString(),
            'start_balance' => 1000,
            'start_balance_at' => now(),
            'profit' => 23,
        ]);

        $service = new DailyPerformanceService(
            Mockery::mock(BybitExchangeService::class),
            Mockery::mock(TradingLogger::class),
        );

        $this->assertEqualsWithDelta(2.3, $service->profitPct($bot), 0.001);
    }

    public function test_record_closed_trade_updates_bot_stats(): void
    {
        $bot = $this->makeBot();

        BotStat::query()->create([
            'bot_id' => $bot->id,
            'date' => now()->timezone(config('app.timezone'))->toDateString(),
            'start_balance' => 1000,
            'profit' => 0,
            'total_trades' => 0,
            'wins' => 0,
            'losses' => 0,
        ]);

        $trade = Trade::factory()->create([
            'bot_id' => $bot->id,
            'profit_loss' => 12.5,
            'closed_at' => now(),
        ]);

        $service = new DailyPerformanceService(
            Mockery::mock(BybitExchangeService::class),
            Mockery::mock(TradingLogger::class),
        );
        $service->recordClosedTrade($trade);

        $stat = BotStat::query()->where('bot_id', $bot->id)->first();

        $this->assertEqualsWithDelta(12.5, (float) $stat->profit, 0.001);
        $this->assertSame(1, $stat->total_trades);
        $this->assertSame(1, $stat->wins);
        $this->assertSame(0, $stat->losses);
    }

    private function makeBot(): Bot
    {
        $user = User::factory()->create();
        $strategy = Strategy::query()->create([
            'name' => 'Test Strategy',
            'is_active' => true,
        ]);
        $exchangeAccount = ExchangeAccount::factory()->for($user)->create();

        return Bot::factory()->for($user)->create([
            'exchange_account_id' => $exchangeAccount->id,
            'strategy_id' => $strategy->id,
        ]);
    }
}