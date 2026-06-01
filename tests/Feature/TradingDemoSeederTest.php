<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotRun;
use App\Models\BotStat;
use App\Models\ExchangeAccount;
use App\Models\Order;
use App\Models\Position;
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
        $this->assertDatabaseCount('bot_runs', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('positions', 1);
        $this->assertDatabaseCount('trades', 1);
        $this->assertDatabaseCount('bot_stats', 1);

        $user = User::query()->firstOrFail();
        $strategy = Strategy::query()->firstOrFail();
        $exchangeAccount = ExchangeAccount::query()->firstOrFail();
        $bot = Bot::query()->firstOrFail();
        $run = BotRun::query()->firstOrFail();
        $order = Order::query()->firstOrFail();
        $position = Position::query()->firstOrFail();
        $trade = Trade::query()->firstOrFail();
        $stat = BotStat::query()->firstOrFail();

        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('BTC Trend Momentum', $strategy->name);
        $this->assertSame('BTC Trend Bot', $bot->name);
        $this->assertSame('BTCUSDT', $run->symbol);
        $this->assertSame('BTCUSDT', $order->symbol);
        $this->assertSame('BTCUSDT', $position->symbol);
        $this->assertSame('BTCUSDT', $trade->symbol);
        $this->assertSame($bot->id, $stat->bot_id);
        $this->assertSame($bot->id, $run->bot_id);
        $this->assertSame($bot->id, $order->bot_id);
        $this->assertSame($bot->id, $position->bot_id);
        $this->assertSame($bot->id, $trade->bot_id);
        $this->assertSame($bot->id, $exchangeAccount->bots()->firstOrFail()->id);
    }
}
