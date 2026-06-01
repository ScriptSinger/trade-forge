<?php

namespace App\Models;

use App\Enums\StrategyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Strategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => StrategyType::class,
            'settings' => 'array',
            'is_active' => 'bool',
        ];
    }

    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }
}
