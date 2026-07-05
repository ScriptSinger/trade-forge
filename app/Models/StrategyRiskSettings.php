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
        'max_positions',
        'max_risk_per_trade',
        'daily_target_enabled',
        'daily_profit_target_pct',
    ];

    protected function casts(): array
    {
        return [
            'sl_multiplier' => 'float',
            'tp_multiplier' => 'float',
            'trailing_pct' => 'float',
            'max_positions' => 'integer',
            'max_risk_per_trade' => 'float',
            'daily_target_enabled' => 'bool',
            'daily_profit_target_pct' => 'float',
        ];
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }
}
