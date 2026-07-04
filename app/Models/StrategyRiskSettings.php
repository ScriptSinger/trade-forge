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
    ];

    protected function casts(): array
    {
        return [
            'sl_multiplier' => 'float',
            'tp_multiplier' => 'float',
            'trailing_pct' => 'float',
            'max_positions' => 'integer',
            'max_risk_per_trade' => 'float',
        ];
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }
}
