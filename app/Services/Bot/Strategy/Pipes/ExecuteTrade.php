<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Enums\TradeContextStatus;
use App\Enums\TradeSignal;
use App\Services\Bot\BotRunLogger;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Order\OrderService;
use App\Services\Position\PositionService;
use Closure;
use Illuminate\Support\Facades\Log;

class ExecuteTrade implements PipeContract
{
    use BlocksTradeContext;

    public function __construct(
        private BybitExchangeService $exchange,
        private OrderService $orders,
        private PositionService $positions,
        private BotRunLogger $logger
    ) {}

    public function handle(TradeContext $context, Closure $next): mixed
    {
        if ($context->isBlocked || $context->signal !== TradeSignal::Buy) {
            return $context;
        }

        if ($context->quantity <= 0) {
            return $this->block($context, 'Invalid order quantity');
        }

        Log::info("Pipeline: EXECUTING trade for {$context->symbol} ({$context->mode} mode)");

        $bot = $context->bot;
        $account = $bot->exchangeAccount;
        $signal = $context->signal;
        $qty = $context->quantity;
        $price = (float) $context->lastCandle()['close'];

        $this->logger->log($bot, $signal, $price, $context->indicators, $context->symbol);

        $orderResponse = $this->exchange->placeMarketOrder(
            account: $account,
            symbol: $context->symbol,
            side: $signal->value,
            qty: $qty,
        );

        Log::info('Pipeline: Exchange response: ' . json_encode($orderResponse));

        $retCode = (int) ($orderResponse['retCode'] ?? -1);

        if ($retCode !== 0) {
            $message = (string) ($orderResponse['retMsg'] ?? 'Exchange order failed');

            return $this->block($context, "Exchange error [{$retCode}]: {$message}", TradeContextStatus::Failed);
        }

        $order = $this->orders->storeFromResponse(
            bot: $bot,
            account: $account,
            symbol: $context->symbol,
            side: $signal,
            qty: $qty,
            response: $orderResponse,
            marketPrice: $price,
        );

        $this->positions->syncFromOrder(
            bot: $bot,
            order: $order,
            signal: $signal,
            mode: $context->mode,
            sl: $context->stopLoss,
            tp: $context->takeProfit,
        );

        $context->status = TradeContextStatus::Executed;
        $this->logger->success($bot, $signal, [
            'price' => $price,
            'qty' => $qty,
            'order_id' => $order->id ?? null,
            'mode' => $context->mode,
            'sl' => $context->stopLoss,
            'tp' => $context->takeProfit,
        ], $context->symbol, $price, "Order placed successfully ({$context->mode} mode)");

        return $context;
    }
}