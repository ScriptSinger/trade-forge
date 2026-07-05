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
        array $response,
        float $marketPrice = 0
    ): Order {
        $retCode = $response['retCode'] ?? -1;
        $orderId = $response['result']['orderId'] ?? null;
        
        // In Bybit V5 market orders, the exact price is known after execution.
        // If not in response, we use the market price at the moment of analysis.
        $execPrice = $response['result']['avgPrice'] ?? $response['result']['price'] ?? $marketPrice;

        // Map side to OrderSide enum
        if ($side instanceof \App\Enums\TradeSignal) {
            $side = $side->value;
        }

        if (is_string($side)) {
            $side = OrderSide::tryFrom(strtolower($side)) ?? OrderSide::Buy;
        }

        // Pipeline quantity is always base-asset amount (coins).
        $actualQty = (float) $qty;

        return Order::create([
            'bot_id' => $bot->id,
            'exchange_account_id' => $account->id,
            'symbol' => $symbol,
            'side' => $side,
            'price' => (float) $execPrice,
            'type' => OrderType::Market, 
            'quantity' => $actualQty,
            'status' => $retCode === 0 ? OrderStatus::Filled : OrderStatus::Failed,
            'exchange_order_id' => $orderId,
            'raw_response' => $response,
        ]);
    }
}
