<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyBtcTrendFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategy_id',
        'enabled',
        'benchmark_symbol',
        'benchmark_interval',
        'ema_fast',
        'ema_slow',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
        ];
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }
}
