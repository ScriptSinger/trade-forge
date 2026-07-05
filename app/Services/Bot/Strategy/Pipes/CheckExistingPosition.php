<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Enums\PositionStatus;
use App\Enums\TradeContextStatus;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckExistingPosition implements PipeContract
{
    use BlocksTradeContext;

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $exists = $context->bot->positions()
            ->where('symbol', $context->symbol)
            ->where('status', PositionStatus::Open)
            ->exists();

        if ($exists) {
            Log::info("Pipeline: Skipping {$context->symbol} - Position already exists.");

            return $this->block(
                $context,
                "Position already exists for {$context->symbol}",
                TradeContextStatus::Skipped,
            );
        }

        return $next($context);
    }
}