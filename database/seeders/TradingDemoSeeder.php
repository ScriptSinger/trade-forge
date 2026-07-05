<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use App\Enums\BotRunStatus;
use App\Enums\BotStatus;
use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PositionStatus;
use App\Enums\StrategyType;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\BotRun;
use App\Models\BotStat;
use App\Models\ExchangeAccount;
use App\Models\Order;
use App\Models\Position;
use App\Models\Strategy;
use App\Models\StrategyBtcTrendFilter;
use App\Models\StrategyEntrySettings;
use App\Models\StrategyRiskSettings;
use App\Models\Trade;
use App\Models\User;
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
            ['name' => 'BTC Trend Momentum'],
            [
                'type' => StrategyType::Trend->value,
                'is_active' => true,
            ],
        );

        StrategyEntrySettings::query()->updateOrCreate(
            ['strategy_id' => $strategy->id],
            [
                'interval' => 1,
                'period' => 20,
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
                'max_risk_per_trade' => 1.0,
                'daily_target_enabled' => true,
                'daily_profit_target_pct' => 2.30,
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

        $bot = Bot::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_account_id' => $exchangeAccount->id,
                'strategy_id' => $strategy->id,
                'name' => 'BTC Trend Bot',
            ],
            [
                'risk_per_trade' => 1.00,
                'max_open_positions' => 1,
                'status' => BotStatus::Active->value,
                'last_run_at' => $now,
            ],
        );

        $order = Order::query()->updateOrCreate(
            [
                'bot_id' => $bot->id,
                'exchange_account_id' => $exchangeAccount->id,
                'symbol' => 'BTCUSDT',
                'side' => OrderSide::Buy->value,
                'type' => OrderType::Market->value,
                'status' => OrderStatus::Filled->value,
            ],
            [
                'price' => '95000.00000000',
                'quantity' => '0.01000000',
                'exchange_order_id' => 'demo-btc-buy-001',
                'raw_response' => [
                    'success' => true,
                    'exchange' => 'bybit',
                ],
            ],
        );

        $runPayload = [
            'market_price' => '95000.00000000',
            'quantity' => '0.01000000',
            'mode' => 'Sniper',
            'stop_loss' => '93000.00000000',
            'take_profit' => '98000.00000000',
            'order_id' => $order->id,
            'signal' => TradeSignal::Buy->value,
            'status' => BotRunStatus::Success->value,
            'indicators' => [
                'ema_fast' => 94850.12,
                'ema_slow' => 94120.83,
                'rsi' => 54.32,
                'adx' => 21.8,
                'atr' => 840.15,
            ],
            'reason' => 'Order placed successfully (Sniper mode)',
        ];

        $run = BotRun::query()
            ->where('bot_id', $bot->id)
            ->where('symbol', 'BTCUSDT')
            ->first();

        if ($run) {
            $run->forceFill($runPayload)->save();
        } else {
            BotRun::query()->create([
                'bot_id' => $bot->id,
                'symbol' => 'BTCUSDT',
                ...$runPayload,
            ]);
        }

        Position::query()->updateOrCreate(
            [
                'bot_id' => $bot->id,
                'symbol' => 'BTCUSDT',
                'status' => PositionStatus::Open->value,
            ],
            [
                'mode' => 'Sniper',
                'entry_price' => '95000.00000000',
                'quantity' => '0.01000000',
                'sl' => '93000.00000000',
                'tp' => '98000.00000000',
                'be_activated' => true,
                'trailing_active' => false,
                'half_sold' => false,
                'opened_at' => $now->subHours(4),
                'closed_at' => null,
            ],
        );

        Trade::query()->updateOrCreate(
            [
                'bot_id' => $bot->id,
                'symbol' => 'BTCUSDT',
                'opened_at' => $now->subDay(),
                'closed_at' => $now->subDay()->addHours(6),
            ],
            [
                'entry_price' => '92000.00000000',
                'exit_price' => '94800.00000000',
                'quantity' => '0.01000000',
                'profit_loss' => '28.00000000',
                'profit_percent' => '3.04',
                'fees' => '1.50000000',
            ],
        );

        BotStat::query()->updateOrCreate(
            [
                'bot_id' => $bot->id,
                'date' => $now->startOfDay(),
            ],
            [
                'total_trades' => 1,
                'wins' => 1,
                'losses' => 0,
                'winrate' => '100.00',
                'profit' => '28.00000000',
                'fees' => '1.50000000',
            ],
        );
    }
}
