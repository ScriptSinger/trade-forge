<?php

namespace Database\Factories;

use App\Models\Bot;
use App\Models\BotStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BotStat>
 */
class BotStatFactory extends Factory
{
    protected $model = BotStat::class;

    public function definition(): array
    {
        $wins = fake()->numberBetween(0, 25);
        $losses = fake()->numberBetween(0, 25);
        $totalTrades = $wins + $losses;

        return [
            'bot_id' => Bot::factory(),
            'date' => now()->toDateString(),
            'total_trades' => $totalTrades,
            'wins' => $wins,
            'losses' => $losses,
            'winrate' => $totalTrades > 0 ? round(($wins / $totalTrades) * 100, 2) : 0,
            'profit' => fake()->randomFloat(8, -1000, 5000),
            'fees' => fake()->randomFloat(8, 0, 100),
        ];
    }
}
