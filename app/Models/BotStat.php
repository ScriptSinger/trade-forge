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
