<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BotStatus;
use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\StrategyBtcTrendFilter;
use App\Models\StrategyEntrySettings;
use App\Models\StrategyRiskSettings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class TradingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::parse('2026-06-01 12:00:00', 'UTC');

        $user = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ],
        );

        $strategy = Strategy::query()->updateOrCreate(
            ['name' => 'Spot Breakout Mode 4'],
            [
                'is_active' => true,
            ],
        );

        StrategyEntrySettings::query()->updateOrCreate(
            ['strategy_id' => $strategy->id],
            [
                'strategy_mode' => 4,
                'interval' => 15,
                'period' => 20,
                'kline_limit' => 1000,
                'ema_fast' => 50,
                'ema_slow' => 200,
                'adx_min' => 25,
                'trend_adx_threshold' => 30,
                'rsi_limit_sniper' => 55,
                'rsi_limit_hybrid' => 75,
            ],
        );

        StrategyRiskSettings::query()->updateOrCreate(
            ['strategy_id' => $strategy->id],
            [
                'sl_multiplier' => 2.0,
                'tp_multiplier' => 3.0,
                'trailing_pct' => 1.5,
                'max_positions' => 3,
                'max_risk_per_trade' => 0.02,
                'daily_target_enabled' => true,
                'daily_profit_target_pct' => 2.30,
                'spot_fee_rate' => 0.001,
                'min_order_usdt' => 5,
                'max_balance_pct' => 0.30,
                'free_balance_buffer' => 0.98,
                'scanner_cache_ttl' => 7200,
            ],
        );

        StrategyBtcTrendFilter::query()->updateOrCreate(
            ['strategy_id' => $strategy->id],
            [
                'enabled' => true,
                'benchmark_symbol' => 'BTCUSDT',
                'benchmark_interval' => 60,
                'ema_fast' => 50,
                'ema_slow' => 200,
            ],
        );

        $exchangeAccount = ExchangeAccount::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange' => ExchangeProvider::Bybit->value,
                'name' => 'Bybit Main',
            ],
            [
                'api_key' => env('BYBIT_API_KEY', 'demo-bybit-api-key'),
                'api_secret' => env('BYBIT_API_SECRET', 'demo-bybit-api-secret'),
                'api_url' => env('BYBIT_BASE_URL', 'https://api-testnet.bybit.com'),
                'status' => ExchangeAccountStatus::Active->value,
                'last_checked_at' => $now,
            ],
        );

        Bot::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_account_id' => $exchangeAccount->id,
                'strategy_id' => $strategy->id,
                'name' => 'Bybit Spot Bot',
            ],
            [
                'status' => BotStatus::Paused->value,
                'last_run_at' => null,
            ],
        );
    }
}
