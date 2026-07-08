<?php

namespace App\Models;

use App\Casts\ResilientEncrypted;
use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exchange',
        'name',
        'api_key',
        'api_secret',
        'api_url',
        'status',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'exchange' => ExchangeProvider::class,
            'api_key' => ResilientEncrypted::class,
            'api_secret' => ResilientEncrypted::class,
            'status' => ExchangeAccountStatus::class,
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
