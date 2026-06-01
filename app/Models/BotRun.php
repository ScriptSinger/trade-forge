<?php

namespace App\Models;

use App\Enums\BotRunStatus;
use App\Enums\TradeSignal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'symbol',
        'market_price',
        'signal',
        'indicators',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'market_price' => 'decimal:8',
            'signal' => TradeSignal::class,
            'indicators' => 'array',
            'status' => BotRunStatus::class,
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
