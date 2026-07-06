<?php

namespace Database\Factories;

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
            'is_active' => true,
        ];
    }
}