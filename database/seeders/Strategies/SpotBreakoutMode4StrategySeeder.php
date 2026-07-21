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
 * Default live preset: strategy_mode 4 (Smart Hybrid).
 * Interval / period / indicators live in entry settings, not in the name.
 *
 * Naming: {family}-v{MAJOR.MINOR.PATCH}
 * Bump VERSION only when publishing a new preset (keeps old rows if name changes).
 */
class SpotBreakoutMode4StrategySeeder extends Seeder
{
    /** Stable family slug (do not change for a version bump). */
    public const FAMILY = 'smart-hybrid';

    /** Semver of this preset. */
    public const VERSION = '1.0.0';

    /** Full name in DB / MoonShine: family-vVERSION */
    public const STRATEGY_NAME = self::FAMILY.'-v'.self::VERSION;

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