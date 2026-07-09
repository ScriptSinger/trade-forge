<?php

declare(strict_types=1);

namespace Database\Seeders\Strategies;

use App\Models\Strategy;
use App\Models\StrategyBtcTrendFilter;
use App\Models\StrategyEntrySettings;
use App\Models\StrategyRiskSettings;
use Illuminate\Database\Seeder;

/**
 * Strategy preset from Andrew_sample/pro_trade.py V6.3.
 *
 * Seeds only the strategy graph (entry + risk + BTC filter).
 * Attach to any bot in MoonShine: Strategy → "Andrew Pro V6.3".
 *
 * Not mapped yet: dual daily profit targets (2.0% flat / 2.7% trend),
 * daily loss limit 2.5%, pullback entry.
 */
class AndrewProV63StrategySeeder extends Seeder
{
    public const STRATEGY_NAME = 'Andrew Pro V6.3';

    public function run(): void
    {
        $strategy = Strategy::query()->updateOrCreate(
            ['name' => self::STRATEGY_NAME],
            ['is_active' => true],
        );

        StrategyEntrySettings::query()->updateOrCreate(
            ['strategy_id' => $strategy->id],
            [
                'strategy_mode' => 4,
                'interval' => 60,
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
                'tp_multiplier' => 2.5,
                'trailing_pct' => 1.5,
                'max_positions' => 3,
                'max_risk_per_trade' => 0.01,
                'daily_target_enabled' => true,
                'daily_profit_target_pct' => 2.0,
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
    }
}