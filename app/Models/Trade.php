<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'symbol',
        'entry_price',
        'exit_price',
        'quantity',
        'profit_loss',
        'profit_percent',
        'fees',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_price' => 'decimal:8',
            'exit_price' => 'decimal:8',
            'quantity' => 'decimal:8',
            'profit_loss' => 'decimal:8',
            'profit_percent' => 'decimal:2',
            'fees' => 'decimal:8',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
