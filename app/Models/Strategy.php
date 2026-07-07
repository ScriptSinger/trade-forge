<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Strategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    public function entrySettings(): HasOne
    {
        return $this->hasOne(StrategyEntrySettings::class);
    }

    public function riskSettings(): HasOne
    {
        return $this->hasOne(StrategyRiskSettings::class);
    }

    public function btcTrendFilter(): HasOne
    {
        return $this->hasOne(StrategyBtcTrendFilter::class);
    }
}
