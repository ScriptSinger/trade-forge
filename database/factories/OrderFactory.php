<?php

namespace Database\Factories;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(),
            'exchange_account_id' => ExchangeAccount::factory(),
            'symbol' => 'BTCUSDT',
            'side' => OrderSide::Buy->value,
            'type' => OrderType::Market->value,
            'price' => fake()->randomFloat(8, 10000, 100000),
            'quantity' => fake()->randomFloat(8, 0.001, 0.5),
            'status' => OrderStatus::New->value,
            'exchange_order_id' => fake()->uuid(),
            'raw_response' => [
                'success' => true,
                'exchange' => 'bybit',
            ],
        ];
    }
}
