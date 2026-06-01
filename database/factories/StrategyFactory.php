<?php

namespace Database\Factories;

use App\Enums\StrategyType;
use App\Models\Strategy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Strategy>
 */
class StrategyFactory extends Factory
{
    protected $model = Strategy::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => StrategyType::Breakout->value,
            'settings' => [
                'ema_fast' => 12,
                'ema_slow' => 26,
                'rsi_period' => 14,
                'adx_period' => 14,
                'atr_period' => 14,
            ],
            'is_active' => true,
        ];
    }
}
