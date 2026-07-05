<?php

namespace Database\Factories;

use App\Enums\PositionStatus;
use App\Models\Bot;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(),
            'symbol' => 'BTCUSDT',
            'mode' => 'Sniper',
            'entry_price' => fake()->randomFloat(8, 10000, 100000),
            'quantity' => fake()->randomFloat(8, 0.001, 0.5),
            'sl' => fake()->optional()->randomFloat(8, 9000, 99000),
            'tp' => fake()->optional()->randomFloat(8, 10000, 110000),
            'be_activated' => false,
            'trailing_active' => false,
            'half_sold' => false,
            'status' => PositionStatus::Open->value,
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }
}
