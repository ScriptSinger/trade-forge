<?php

namespace Database\Factories;

use App\Enums\BotRunStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\BotRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BotRun>
 */
class BotRunFactory extends Factory
{
    protected $model = BotRun::class;

    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(),
            'symbol' => 'BTCUSDT',
            'market_price' => fake()->randomFloat(8, 10000, 100000),
            'signal' => TradeSignal::Hold->value,
            'indicators' => [
                'ema_fast' => 65000.0,
                'ema_slow' => 64000.0,
                'rsi' => 52.1,
            ],
            'reason' => fake()->sentence(),
            'status' => BotRunStatus::Success->value,
        ];
    }
}
