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
        'quantity',
        'mode',
        'stop_loss',
        'take_profit',
        'order_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'market_price' => 'decimal:8',
            'quantity' => 'decimal:8',
            'stop_loss' => 'decimal:8',
            'take_profit' => 'decimal:8',
            'signal' => TradeSignal::class,
            'indicators' => 'array',
            'status' => BotRunStatus::class,
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function notionalUsdt(): ?float
    {
        if ($this->quantity === null || $this->market_price === null) {
            return null;
        }

        return (float) $this->quantity * (float) $this->market_price;
    }
}
