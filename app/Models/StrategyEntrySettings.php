<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyEntrySettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategy_id',
        'interval',
        'period',
        'kline_limit',
        'ema_fast',
        'ema_slow',
        'adx_min',
        'trend_adx_threshold',
        'rsi_limit_sniper',
        'rsi_limit_hybrid',
    ];

    protected function casts(): array
    {
        return [
            'interval' => 'integer',
            'period' => 'integer',
            'kline_limit' => 'integer',
            'ema_fast' => 'integer',
            'ema_slow' => 'integer',
            'adx_min' => 'float',
            'trend_adx_threshold' => 'integer',
            'rsi_limit_sniper' => 'float',
            'rsi_limit_hybrid' => 'float',
        ];
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }
}
