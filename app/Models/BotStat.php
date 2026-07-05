<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'date',
        'start_balance',
        'start_balance_at',
        'total_trades',
        'wins',
        'losses',
        'winrate',
        'profit',
        'fees',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_balance' => 'decimal:8',
            'start_balance_at' => 'datetime',
            'total_trades' => 'integer',
            'wins' => 'integer',
            'losses' => 'integer',
            'winrate' => 'decimal:2',
            'profit' => 'decimal:8',
            'fees' => 'decimal:8',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
