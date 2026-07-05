<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotStat;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TradingDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_demo_trading_graph_without_duplicates(): void
    {
        Artisan::call('db:seed');
        Artisan::call('db:seed');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('strategies', 1);
        $this->assertDatabaseCount('exchange_accounts', 1);
        $this->assertDatabaseCount('bots', 1);
        $this->assertDatabaseCount('bot_runs', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('positions', 0);
        $this->assertDatabaseCount('trades', 1);
        $this->assertDatabaseCount('bot_stats', 1);

        $user = User::query()->firstOrFail();
        $strategy = Strategy::query()->firstOrFail();
        $exchangeAccount = ExchangeAccount::query()->firstOrFail();
        $bot = Bot::query()->firstOrFail();
        $trade = Trade::query()->firstOrFail();
        $stat = BotStat::query()->firstOrFail();

        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('Spot Breakout Mode 4', $strategy->name);
        $this->assertSame('Bybit Spot Bot', $bot->name);
        $this->assertSame('BTCUSDT', $trade->symbol);
        $this->assertSame($bot->id, $stat->bot_id);
        $this->assertSame($bot->id, $trade->bot_id);
        $this->assertSame($bot->id, $exchangeAccount->bots()->firstOrFail()->id);
        $this->assertNull($bot->last_run_at);
    }
}