<?php

namespace Database\Factories;

use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use App\Models\ExchangeAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeAccount>
 */
class ExchangeAccountFactory extends Factory
{
    protected $model = ExchangeAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exchange' => ExchangeProvider::Bybit->value,
            'name' => fake()->company(),
            'api_key' => fake()->lexify('key_??????????'),
            'api_secret' => fake()->lexify('secret_????????????????'),
            'testnet' => false,
            'status' => ExchangeAccountStatus::Active->value,
            'last_checked_at' => now(),
        ];
    }
}
