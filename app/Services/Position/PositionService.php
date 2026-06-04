<?php

namespace App\Services\Position;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\PositionStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\Order;
use App\Models\Position;

class PositionService
{
    /**
     * Synchronize positions based on a executed order.
     */
    public function syncFromOrder(Bot $bot, Order $order, TradeSignal|string $signal): void
    {
        // Only sync if order is filled
        if ($order->status !== OrderStatus::Filled) {
            return;
        }

        if ($order->side === OrderSide::Buy) {
            $this->openPosition($bot, $order);
        } elseif ($order->side === OrderSide::Sell) {
            $this->closePositions($bot, $order);
        }
    }

    /**
     * Open a new position.
     */
    private function openPosition(Bot $bot, Order $order): void
    {
        Position::create([
            'bot_id' => $bot->id,
            'symbol' => $order->symbol,
            'entry_price' => $order->price ?? 0, // In reality, we'd get this from fill details
            'quantity' => $order->quantity,
            'status' => PositionStatus::Open,
            'opened_at' => now(),
        ]);
    }

    /**
     * Close existing open positions for the bot and symbol.
     */
    private function closePositions(Bot $bot, Order $order): void
    {
        $bot->positions()
            ->where('symbol', $order->symbol)
            ->where('status', PositionStatus::Open)
            ->update([
                'status' => PositionStatus::Closed,
                'closed_at' => now(),
            ]);
    }
}
