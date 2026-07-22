<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyRiskSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategy_id',
        'sl_multiplier',
        'tp_multiplier',
        'trailing_pct',
        'hybrid_tp_portion',
        'hybrid_be_multiplier',
        'max_positions',
        'max_risk_per_trade',
        'daily_target_enabled',
        'daily_profit_target_pct',
        'spot_fee_rate',
        'min_order_usdt',
        'max_balance_pct',
        'free_balance_buffer',
        'scanner_cache_ttl',
        'scanner_excluded_patterns',
    ];

    protected function casts(): array
    {
        return [
            'sl_multiplier' => 'float',
            'tp_multiplier' => 'float',
            'trailing_pct' => 'float',
            'hybrid_tp_portion' => 'float',
            'hybrid_be_multiplier' => 'float',
            'max_positions' => 'integer',
            'max_risk_per_trade' => 'float',
            'daily_target_enabled' => 'bool',
            'daily_profit_target_pct' => 'float',
            'spot_fee_rate' => 'float',
            'min_order_usdt' => 'float',
            'max_balance_pct' => 'float',
            'free_balance_buffer' => 'float',
            'scanner_cache_ttl' => 'integer',
            'scanner_excluded_patterns' => 'array',
        ];
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }
}
