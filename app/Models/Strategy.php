<?php

namespace App\Models;

use App\Enums\StrategyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Strategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => StrategyType::class,
            'is_active' => 'bool',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Strategy $strategy): void {
            if ($strategy->type === null) {
                $strategy->type = StrategyType::Hybrid;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

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
