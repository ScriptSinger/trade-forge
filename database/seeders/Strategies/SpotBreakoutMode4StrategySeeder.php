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
 * smart-hybrid-v1.0.0 — 1:1 with sample/ (aza_trade.py + config_pro.py + core_math.py).
 *
 * Source map:
 *   STRATEGY_MODE=4, TREND_ADX=30, TRAILING_STEP=0.985  → sample/aza_trade.py
 *   TIMEFRAME=15m, RISK=0.02, TP_MULT=3, SL_MULT=2.0,
 *   MAX_POSITIONS=3, ADX_THRESHOLD=25, UPDATE_INTERVAL=7200,
 *   DAILY_PROFIT_TARGET_PCT=2.3, GLOBAL_TF=1h           → sample/config_pro.py
 *   ema50/200, resistance/vol_ma window=20, limit=1000,
 *   fee 0.001, RSI sniper 55 / hybrid 75               → sample/core_math.py + aza_trade entry
 *
 * Naming: {family}-v{MAJOR.MINOR.PATCH}
 * Bump VERSION only when publishing a new preset.
 */
class SpotBreakoutMode4StrategySeeder extends Seeder
{
    public const FAMILY = 'smart-hybrid';

    public const VERSION = '1.0.0';

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
                // aza_trade: STRATEGY_MODE = 4 (Smart Hybrid)
                'strategy_mode' => 4,
                // config_pro: TIMEFRAME = '15m'
                'interval' => 15,
                // core_math: resistance / vol_ma rolling(20)
                'period' => 20,
                // core_math: fetch(..., limit=1000)
                'kline_limit' => 1000,
                // core_math: ema50 / ema200
                'ema_fast' => 50,
                'ema_slow' => 200,
                // config_pro: ADX_THRESHOLD = 25  (aza: if adx_val < 25: continue)
                'adx_min' => 25,
                // aza_trade: TREND_ADX = 30  (Hybrid if ADX > 30 else Sniper)
                'trend_adx_threshold' => 30,
                // aza_trade: sniper if rsi_val > 55: continue
                'rsi_limit_sniper' => 55,
                // aza_trade: hybrid if rsi_val > 75: continue
                'rsi_limit_hybrid' => 75,
            ],
        );

        StrategyRiskSettings::query()->updateOrCreate(
            ['strategy_id' => $strategy->id],
            [
                // config_pro: SL_MULT = 2.0
                'sl_multiplier' => 2.0,
                // config_pro: TP_MULT = 3
                'tp_multiplier' => 3.0,
                // aza_trade: TRAILING_STEP = 0.985 → 1.5%
                'trailing_pct' => 1.5,
                // aza_trade: sell(..., portion=0.5) on hybrid TP
                'hybrid_tp_portion' => 0.5,
                // aza_trade: SL → entry * 1.0025 after half TP
                'hybrid_be_multiplier' => 1.0025,
                // config_pro: MAX_POSITIONS = 3
                'max_positions' => 3,
                // config_pro: RISK = 0.02
                'max_risk_per_trade' => 0.02,
                // config_pro: DAILY_PROFIT_TARGET_PCT = 2.3
                'daily_target_enabled' => true,
                'daily_profit_target_pct' => 2.30,
                // aza_trade sell(): fee 0.001 per side
                'spot_fee_rate' => 0.001,
                // Bybit spot minimum (infra, not in sample)
                'min_order_usdt' => 5,
                // Cap single order vs wallet (infra safety; sample has no explicit cap)
                'max_balance_pct' => 0.30,
                'free_balance_buffer' => 0.98,
                // config_pro: UPDATE_INTERVAL = 7200
                'scanner_cache_ttl' => 7200,
                'scanner_excluded_patterns' => ScannerSymbolFilter::defaults(),
            ],
        );

        // core_math btc_ok(): fetch('BTC/USDT', GLOBAL_TF) GLOBAL_TF='1h', ema50 > ema200
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
