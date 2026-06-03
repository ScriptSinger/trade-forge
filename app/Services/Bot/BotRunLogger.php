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
    public function error(Bot $bot, string $reason, array $context = []): void
    {
        $bot->runs()->create([
            'symbol' => $bot->symbol,
            'reason' => $reason,
            'indicators' => $context,
            'status' => BotRunStatus::Failed,
            'signal' => null,
            'market_price' => null,
        ]);
    }

    /**
     * Log general information during a run.
     */
    public function info(Bot $bot, string $reason, array $context = []): void
    {
        $bot->runs()->create([
            'symbol' => $bot->symbol,
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
    public function log(Bot $bot, TradeSignal|string $signal, $price, array $indicators = []): void
    {
        if (is_string($signal)) {
            $signal = TradeSignal::tryFrom(strtolower($signal)) ?? TradeSignal::Hold;
        }

        $bot->runs()->create([
            'symbol' => $bot->symbol,
            'market_price' => $price,
            'signal' => $signal,
            'indicators' => $indicators,
            'status' => BotRunStatus::Processing,
        ]);
    }

    /**
     * Log a successful completion of the bot run.
     */
    public function success(Bot $bot, TradeSignal|string $signal, array $context = []): void
    {
        if (is_string($signal)) {
            $signal = TradeSignal::tryFrom(strtolower($signal)) ?? TradeSignal::Hold;
        }

        $bot->runs()->create([
            'symbol' => $bot->symbol,
            'signal' => $signal,
            'indicators' => $context,
            'status' => BotRunStatus::Success,
        ]);
    }
}
