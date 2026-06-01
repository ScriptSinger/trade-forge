<?php

namespace App\Models;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'exchange_account_id',
        'symbol',
        'side',
        'type',
        'price',
        'quantity',
        'status',
        'exchange_order_id',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'side' => OrderSide::class,
            'type' => OrderType::class,
            'price' => 'decimal:8',
            'quantity' => 'decimal:8',
            'status' => OrderStatus::class,
            'raw_response' => 'array',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function exchangeAccount(): BelongsTo
    {
        return $this->belongsTo(ExchangeAccount::class);
    }
}
