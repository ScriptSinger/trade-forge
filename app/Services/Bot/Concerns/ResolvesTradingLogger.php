<?php

namespace App\Services\Bot\Concerns;

use App\Services\Bot\Engine\TradingLogger;

trait ResolvesTradingLogger
{
    private ?TradingLogger $tradingLogger = null;

    protected function tradingLog(): TradingLogger
    {
        return $this->tradingLogger ??= app(TradingLogger::class);
    }
}
