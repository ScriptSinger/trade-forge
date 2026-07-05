<?php

namespace App\Models;

use App\Enums\PositionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'symbol',
        'mode',
        'entry_price',
        'current_price',
        'pnl_pct',
        'quantity',
        'sl',
        'tp',
        'be_activated',
        'trailing_active',
        'half_sold',
        'status',
        'exit_reason',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_price' => 'decimal:8',
            'quantity' => 'decimal:8',
            'sl' => 'decimal:8',
            'tp' => 'decimal:8',
            'be_activated' => 'boolean',
            'trailing_active' => 'boolean',
            'half_sold' => 'boolean',
            'status' => PositionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
