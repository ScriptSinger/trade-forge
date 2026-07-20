<?php

namespace App\Services\Bot\Engine;

use App\Enums\TradeSignal;
use App\Models\Bot;
use Illuminate\Support\Facades\Log;

class TradingLogger
{
    public function __construct(
        private BotRunLogger $audit,
    ) {}

    public function auditOrderPlaced(
        Bot $bot,
        TradeSignal $signal,
        array $indicators,
        string $symbol,
        float $price,
        float $quantity,
        string $mode,
        ?float $stopLoss,
        ?float $takeProfit,
        ?int $orderId,
        string $reason,
    ): void {
        $this->audit->success(
            bot: $bot,
            signal: $signal,
            indicators: $indicators,
            symbol: $symbol,
            price: $price,
            reason: $reason,
            quantity: $quantity,
            mode: $mode,
            stopLoss: $stopLoss,
            takeProfit: $takeProfit,
            orderId: $orderId,
        );
    }

    public function auditPositionExit(
        Bot $bot,
        TradeSignal $signal,
        string $symbol,
        float $price,
        float $quantity,
        string $reason,
        ?string $mode = null,
    ): void {
        $this->audit->success(
            bot: $bot,
            signal: $signal,
            indicators: [],
            symbol: $symbol,
            price: $price,
            reason: $reason,
            quantity: $quantity,
            mode: $mode,
        );
    }

    public function auditFailed(Bot $bot, string $reason, array $context = [], ?string $symbol = null): void
    {
        $this->audit->error($bot, $reason, $context, $symbol);
    }

    public function auditGuardEvent(Bot $bot, string $reason, array $context = []): void
    {
        $this->audit->info($bot, $reason, $context);
    }

    public function auditTradeEvent(Bot $bot, string $reason, array $context, string $symbol): void
    {
        $this->audit->info($bot, $reason, $context, $symbol);
    }

    public function botInfo(string $message, array $context = []): void
    {
        Log::channel('bot')->info($message, $context);
    }

    public function botWarning(string $message, array $context = []): void
    {
        Log::channel('bot')->warning($message, $context);
    }

    public function botError(string $message, array $context = []): void
    {
        Log::channel('bot')->error($message, $context);
    }

    public function botDebug(string $message, array $context = []): void
    {
        Log::channel('bot')->debug($message, $context);
    }

    public function strategyDebug(string $message, array $context = []): void
    {
        Log::channel('strategy')->debug($message, $context);
    }

    public function orderInfo(string $message, array $context = []): void
    {
        Log::channel('orders')->info($message, $context);
    }

    public function orderWarning(string $message, array $context = []): void
    {
        Log::channel('orders')->warning($message, $context);
    }

    public function orderError(string $message, array $context = []): void
    {
        Log::channel('orders')->error($message, $context);
    }

    public function orderDebug(string $message, array $context = []): void
    {
        Log::channel('orders')->debug($message, $context);
    }

    public function riskInfo(string $message, array $context = []): void
    {
        Log::channel('risk')->info($message, $context);
    }

    public function riskDebug(string $message, array $context = []): void
    {
        Log::channel('risk')->debug($message, $context);
    }

    public function exchangeDebug(string $message, array $context = []): void
    {
        Log::channel('exchange')->debug($message, $context);
    }

    public function exchangeError(string $message, array $context = []): void
    {
        Log::channel('exchange')->error($message, $context);
    }
}
