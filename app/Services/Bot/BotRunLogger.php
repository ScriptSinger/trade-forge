<?php

namespace App\Services\Bot;

use App\Enums\BotRunStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;

class BotRunLogger
{
    public function error(Bot $bot, string $reason, array $context = [], ?string $symbol = null): void
    {
        $bot->runs()->create([
            'symbol' => $symbol ?? 'N/A',
            'reason' => $reason,
            'indicators' => $context,
            'status' => BotRunStatus::Failed,
            'signal' => null,
            'market_price' => null,
        ]);
    }

    public function info(Bot $bot, string $reason, array $context = [], ?string $symbol = null): void
    {
        $bot->runs()->create([
            'symbol' => $symbol ?? 'N/A',
            'reason' => $reason,
            'indicators' => $context,
            'status' => BotRunStatus::Processing,
            'signal' => null,
            'market_price' => null,
        ]);
    }

    public function success(
        Bot $bot,
        TradeSignal|string $signal,
        array $indicators = [],
        ?string $symbol = null,
        $price = null,
        ?string $reason = null,
        ?float $quantity = null,
        ?string $mode = null,
        ?float $stopLoss = null,
        ?float $takeProfit = null,
        ?int $orderId = null,
    ): void {
        if (is_string($signal)) {
            $signal = TradeSignal::tryFrom(strtolower($signal)) ?? TradeSignal::Hold;
        }

        $bot->runs()->create([
            'symbol' => $symbol ?? 'N/A',
            'signal' => $signal,
            'indicators' => $indicators,
            'reason' => $reason,
            'status' => BotRunStatus::Success,
            'market_price' => $price,
            'quantity' => $quantity,
            'mode' => $mode,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'order_id' => $orderId,
        ]);
    }
}
