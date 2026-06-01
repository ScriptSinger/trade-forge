<?php

namespace Database\Factories;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Strategy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bot>
 */
class BotFactory extends Factory
{
    protected $model = Bot::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exchange_account_id' => ExchangeAccount::factory(),
            'strategy_id' => Strategy::factory(),
            'name' => fake()->words(2, true),
            'risk_per_trade' => 1.00,
            'max_open_positions' => 1,
            'status' => BotStatus::Active->value,
            'last_run_at' => now(),
        ];
    }
}
