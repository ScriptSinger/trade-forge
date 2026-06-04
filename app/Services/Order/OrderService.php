<?php

namespace App\Services\Order;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Bot;
use App\Models\ExchangeAccount;
use App\Models\Order;

class OrderService
{
    /**
     * Store an order in the database based on the exchange response.
     */
    public function storeFromResponse(
        Bot $bot,
        ExchangeAccount $account,
        string $symbol,
        $side,
        float $qty,
        array $response
    ): Order {
        $retCode = $response['retCode'] ?? -1;
        $orderId = $response['result']['orderId'] ?? null;

        // Map side to enum
        if (is_string($side)) {
            $side = OrderSide::tryFrom(strtolower($side)) ?? OrderSide::Buy;
        }

        return Order::create([
            'bot_id' => $bot->id,
            'exchange_account_id' => $account->id,
            'symbol' => $symbol,
            'side' => $side,
            'type' => OrderType::Market, // Currently only market orders are supported in BotEngine
            'quantity' => $qty,
            'status' => $retCode === 0 ? OrderStatus::Filled : OrderStatus::Failed,
            'exchange_order_id' => $orderId,
            'raw_response' => $response,
        ]);
    }
}
