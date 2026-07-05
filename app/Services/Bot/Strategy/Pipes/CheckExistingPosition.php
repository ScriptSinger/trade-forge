<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Enums\PositionStatus;
use App\Enums\TradeContextStatus;
use App\Services\Bot\Concerns\ResolvesTradingLogger;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckExistingPosition implements PipeContract
{
    use BlocksTradeContext;
    use ResolvesTradingLogger;

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $exists = $context->bot->positions()
            ->where('symbol', $context->symbol)
            ->where('status', PositionStatus::Open)
            ->exists();

        if ($exists) {
            $this->tradingLog()->strategyDebug('Skipping symbol, position already exists', [
                'symbol' => $context->symbol,
            ]);

            return $this->block(
                $context,
                "Position already exists for {$context->symbol}",
                TradeContextStatus::Skipped,
            );
        }

        return $next($context);
    }
}