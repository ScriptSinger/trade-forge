<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\User;
use Database\Seeders\Strategies\AndrewProV63StrategySeeder;
use Database\Seeders\Strategies\SpotBreakoutMode4StrategySeeder;
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
        $this->assertDatabaseCount('strategies', 2);
        $this->assertDatabaseCount('exchange_accounts', 1);
        $this->assertDatabaseCount('bots', 1);
        $this->assertDatabaseCount('bot_runs', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('positions', 0);
        $this->assertDatabaseCount('trades', 0);
        $this->assertDatabaseCount('bot_stats', 0);

        $user = User::query()->firstOrFail();
        $exchangeAccount = ExchangeAccount::query()->firstOrFail();

        $this->assertSame('test@example.com', $user->email);
        $this->assertTrue(
            Strategy::query()->where('name', SpotBreakoutMode4StrategySeeder::STRATEGY_NAME)->exists()
        );
        $this->assertTrue(
            Strategy::query()->where('name', AndrewProV63StrategySeeder::STRATEGY_NAME)->exists()
        );

        $bot = Bot::query()->where('name', 'Bybit Spot Bot')->firstOrFail();

        $this->assertSame(BotStatus::Paused, $bot->status);
        $this->assertNull($bot->last_run_at);
        $this->assertSame(1, $exchangeAccount->bots()->count());
        $this->assertSame(
            SpotBreakoutMode4StrategySeeder::STRATEGY_NAME,
            $bot->strategy->name,
        );
    }
}
