<?php

namespace App\Services\Bot\Strategy\Pipes\Concerns;

use App\Enums\TradeContextStatus;
use App\Services\Bot\Strategy\TradeContext;

trait BlocksTradeContext
{
    protected function block(
        TradeContext $context,
        string $reason,
        TradeContextStatus $status = TradeContextStatus::Blocked,
    ): TradeContext {
        $context->isBlocked = true;
        $context->reason = $reason;
        $context->status = $status;

        return $context;
    }
}
