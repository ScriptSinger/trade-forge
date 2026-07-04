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
        'ema_fast',
        'ema_slow',
        'adx_min',
    ];

    protected function casts(): array
    {
        return [
            'interval' => 'integer',
            'period' => 'integer',
            'ema_fast' => 'integer',
            'ema_slow' => 'integer',
            'adx_min' => 'float',
        ];
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }
}
