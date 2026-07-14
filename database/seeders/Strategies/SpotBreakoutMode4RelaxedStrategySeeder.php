<?php

declare(strict_types=1);

namespace Database\Seeders\Strategies;

use App\Models\Strategy;
use App\Models\StrategyBtcTrendFilter;
use App\Models\StrategyEntrySettings;
use App\Models\StrategyRiskSettings;
use App\Services\Bot\Market\ScannerSymbolFilter;
use Illuminate\Database\Seeder;

/**
 * Relaxed entry preset for spot breakout (mode 4, 15m).
 *
 * Same risk profile as Spot Breakout Mode 4; looser entry filters for more signals:
 * - adx_min 20 (was 25)
 * - trend_adx_threshold 25 (was 30) — Hybrid RSI limit applies more often
 * - rsi_limit_sniper 62 (was 55)
 * - rsi_limit_hybrid 80 (was 75)
 *
 * Seeds only the strategy graph (entry + risk + BTC filter).
 * Attach to a bot in MoonShine: Strategy → "Spot Breakout Mode 4 Relaxed".
 *
 * php artisan db:seed --class=Database\\Seeders\\Strategies\\SpotBreakoutMode4RelaxedStrategySeeder
 */
class SpotBreakoutMode4RelaxedStrategySeeder extends Seeder
{
    public const STRATEGY_NAME = 'Spot Breakout Mode 4 Relaxed';

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
                'interval' => 15,
                'period' => 20,
                'kline_limit' => 1000,
                'ema_fast' => 50,
                'ema_slow' => 200,
                'adx_min' => 20,
                'trend_adx_threshold' => 25,
                'rsi_limit_sniper' => 62,
                'rsi_limit_hybrid' => 80,
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
                'scanner_excluded_patterns' => ScannerSymbolFilter::defaults(),
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