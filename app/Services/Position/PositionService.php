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
            'entry_price' => (float) $order->price,
            'quantity' => (float) $order->quantity,
            'status' => PositionStatus::Open,
            'opened_at' => now(),
        ]);
    }

    /**
     * Close existing open positions and record a Trade.
     */
    private function closePositions(Bot $bot, Order $order): void
    {
        $openPositions = $bot->positions()
            ->where('symbol', $order->symbol)
            ->where('status', PositionStatus::Open)
            ->get();

        foreach ($openPositions as $position) {
            $exitPrice = (float) $order->price;
            $entryPrice = (float) $position->entry_price;
            $qty = (float) $position->quantity;

            // Simple profit calculation
            $profit = ($exitPrice - $entryPrice) * $qty;
            $profitPercent = ($entryPrice > 0) ? ($profit / ($entryPrice * $qty)) * 100 : 0;

            // Create final trade record
            \App\Models\Trade::create([
                'bot_id' => $bot->id,
                'symbol' => $position->symbol,
                'entry_price' => $entryPrice,
                'exit_price' => $exitPrice,
                'quantity' => $qty,
                'profit_loss' => $profit,
                'profit_percent' => $profitPercent,
                'fees' => 0, // In real world, we should extract fees from exchange response
                'opened_at' => $position->opened_at,
                'closed_at' => now(),
            ]);

            // Mark position as closed
            $position->update([
                'status' => PositionStatus::Closed,
                'closed_at' => now(),
            ]);
        }
    }
}
