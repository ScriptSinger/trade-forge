<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Enums\TradeContextStatus;
use App\Enums\TradeSignal;
use App\Services\Bot\TradingLogger;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use App\Services\Exchange\BybitExchangeService;
use App\Services\Order\OrderService;
use App\Services\Position\PositionService;
use Closure;

class ExecuteTrade implements PipeContract
{
    use BlocksTradeContext;

    public function __construct(
        private BybitExchangeService $exchange,
        private OrderService $orders,
        private PositionService $positions,
        private TradingLogger $log,
    ) {}

    public function handle(TradeContext $context, Closure $next): mixed
    {
        if ($context->isBlocked || $context->signal !== TradeSignal::Buy) {
            return $context;
        }

        if ($context->quantity <= 0) {
            return $this->block($context, 'Invalid order quantity');
        }

        $bot = $context->bot;
        $account = $bot->exchangeAccount;
        $signal = $context->signal;
        $qty = $context->quantity;
        $price = (float) $context->lastCandle()['close'];

        $this->log->orderInfo('Executing trade', [
            'bot_id' => $bot->id,
            'symbol' => $context->symbol,
            'mode' => $context->mode,
            'qty' => $qty,
            'price' => $price,
        ]);

        $orderResponse = $this->exchange->placeMarketOrder(
            account: $account,
            symbol: $context->symbol,
            side: $signal->value,
            qty: $qty,
        );

        $this->log->orderDebug('Exchange response', [
            'symbol' => $context->symbol,
            'response' => $orderResponse,
        ]);

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

        $this->log->auditOrderPlaced(
            bot: $bot,
            signal: $signal,
            indicators: $context->indicators,
            symbol: $context->symbol,
            price: $price,
            quantity: $qty,
            mode: $context->mode,
            stopLoss: $context->stopLoss,
            takeProfit: $context->takeProfit,
            orderId: $order->id ?? null,
            reason: "Order placed successfully ({$context->mode} mode)",
        );

        return $context;
    }
}