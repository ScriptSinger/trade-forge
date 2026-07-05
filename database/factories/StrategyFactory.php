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
            'type' => StrategyType::Hybrid->value,
            'is_active' => true,
        ];
    }
}