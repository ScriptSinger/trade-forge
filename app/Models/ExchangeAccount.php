<?php

namespace App\Models;

use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exchange',
        'name',
        'api_key',
        'api_secret',
        'testnet',
        'status',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'exchange' => ExchangeProvider::class,
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'testnet' => 'bool',
            'status' => ExchangeAccountStatus::class,
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bots(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Bot::class);
    }
}
