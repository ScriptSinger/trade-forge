<?php

namespace App\Services\Bot;

use App\Enums\BotRunStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\BotRun;

class BotRunLogger
{
    /**
     * Log a failed bot run.
     */
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

    /**
     * Log a rejection by strategy filters.
     */
    public function rejected(Bot $bot, string $reason, array $context = [], ?string $symbol = null, $price = null, $signal = null): void
    {
        if (is_string($signal)) {
            $signal = TradeSignal::tryFrom(strtolower($signal)) ?? TradeSignal::Hold;
        }

        $bot->runs()->create([
            'symbol' => $symbol ?? 'N/A',
            'reason' => $reason,
            'indicators' => $context,
            'status' => BotRunStatus::Rejected,
            'signal' => $signal ?? TradeSignal::Hold,
            'market_price' => $price,
        ]);
    }

    /**
     * Log general information during a run.
     */
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

    /**
     * Log the decision (signal) before execution.
     */
    public function log(Bot $bot, TradeSignal|string $signal, $price, array $indicators = [], ?string $symbol = null): void
    {
        if (is_string($signal)) {
            $signal = TradeSignal::tryFrom(strtolower($signal)) ?? TradeSignal::Hold;
        }

        $bot->runs()->create([
            'symbol' => $symbol ?? 'N/A',
            'market_price' => $price,
            'signal' => $signal,
            'indicators' => $indicators,
            'status' => BotRunStatus::Processing,
        ]);
    }

    /**
     * Log a successful completion of the bot run.
     */
    public function success(Bot $bot, TradeSignal|string $signal, array $context = [], ?string $symbol = null, $price = null, ?string $reason = null): void
    {
        if (is_string($signal)) {
            $signal = TradeSignal::tryFrom(strtolower($signal)) ?? TradeSignal::Hold;
        }

        $bot->runs()->create([
            'symbol' => $symbol ?? 'N/A',
            'signal' => $signal,
            'indicators' => $context,
            'reason' => $reason,
            'status' => BotRunStatus::Success,
            'market_price' => $price,
        ]);
    }
}
