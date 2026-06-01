<?php

namespace Database\Factories;

use App\Models\Bot;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trade>
 */
class TradeFactory extends Factory
{
    protected $model = Trade::class;

    public function definition(): array
    {
        $entryPrice = fake()->randomFloat(8, 10000, 100000);
        $exitPrice = fake()->randomFloat(8, 10000, 100000);
        $quantity = fake()->randomFloat(8, 0.001, 0.5);

        return [
            'bot_id' => Bot::factory(),
            'symbol' => 'BTCUSDT',
            'entry_price' => $entryPrice,
            'exit_price' => $exitPrice,
            'quantity' => $quantity,
            'profit_loss' => ($exitPrice - $entryPrice) * $quantity,
            'profit_percent' => fake()->randomFloat(2, -15, 25),
            'fees' => fake()->randomFloat(8, 0, 15),
            'opened_at' => now()->subHours(5),
            'closed_at' => now(),
        ];
    }
}
