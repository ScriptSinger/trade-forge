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
 * Andrew sample pro_trade preset.
 * Interval / period / indicators live in entry settings, not in the name.
 *
 * Naming: {family}-v{MAJOR.MINOR.PATCH}
 * Bump VERSION only when publishing a new preset (keeps old rows if name changes).
 *
 * Not mapped yet: dual daily profit targets (2.0% flat / 2.7% trend),
 * daily loss limit 2.5%, pullback entry.
 */
class AndrewProV63StrategySeeder extends Seeder
{
    /** Stable product/family slug (do not change for a version bump). */
    public const FAMILY = 'andrew-pro';

    /** Semver of this preset (aligned with sample V6.3). */
    public const VERSION = '6.3.0';

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
                'hybrid_tp_portion' => 0.5,
                'hybrid_be_multiplier' => 1.0025,
                'max_positions' => 3,
                'max_risk_per_trade' => 0.01,
                'daily_target_enabled' => true,
                'daily_profit_target_pct' => 2.0,
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