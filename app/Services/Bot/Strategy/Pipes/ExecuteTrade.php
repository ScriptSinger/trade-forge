<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Services\Order\OrderService;
use App\Services\Position\PositionService;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Bot\BotRunLogger;
use Closure;
use Illuminate\Support\Facades\Log;

class ExecuteTrade implements PipeContract
{
    public function __construct(
        private BybitExchangeService $exchange,
        private OrderService $orders,
        private PositionService $positions,
        private BotRunLogger $logger
    ) {}

    public function handle(TradeContext $context, Closure $next): mixed
    {
        Log::info("Pipeline: EXECUTING trade for {$context->symbol} ({$context->mode} mode)");

        $bot = $context->bot;
        $account = $bot->exchangeAccount;
        $signal = $context->signal;
        $qty = $context->quantity;
        $price = $context->lastCandle()['close'];

        // 1. Log BEFORE execution
        $this->logger->log($bot, $signal, $price, $context->indicators, $context->symbol);

        // 2. Place Market Order
        $orderResponse = $this->exchange->placeMarketOrder(
            account: $account,
            symbol: $context->symbol,
            side: $signal->value,
            qty: $qty
        );

        Log::info("Pipeline: Exchange response: " . json_encode($orderResponse));

        // 3. Persist Order
        $order = $this->orders->storeFromResponse(
            bot: $bot,
            account: $account,
            symbol: $context->symbol,
            side: $signal,
            qty: $qty,
            response: $orderResponse,
            marketPrice: (float) $price
        );

        // 4. Sync Position
        $this->positions->syncFromOrder(
            bot: $bot,
            order: $order,
            signal: $signal,
            mode: $context->mode,
            sl: $context->stopLoss,
            tp: $context->takeProfit,
        );

        // 5. Final Log Success
        $context->status = \App\Enums\TradeContextStatus::Executed;
        $this->logger->success($bot, $signal, [
            'price' => $price,
            'qty' => $qty,
            'order_id' => $order->id ?? null,
            'mode' => $context->mode,
            'sl' => $context->stopLoss,
            'tp' => $context->takeProfit,
        ], $context->symbol, $price, "Order placed successfully ({$context->mode} mode)");

        return $next($context);
    }
}
