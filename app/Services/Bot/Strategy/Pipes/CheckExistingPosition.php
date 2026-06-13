<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Enums\PositionStatus;
use App\Enums\TradeContextStatus;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckExistingPosition implements PipeContract
{
    /**
     * Prevents entering a trade if a position for this symbol already exists.
     */
    public function handle(TradeContext $context, Closure $next): mixed
    {
        $exists = $context->bot->positions()
            ->where('symbol', $context->symbol)
            ->where('status', PositionStatus::Open)
            ->exists();

        if ($exists) {
            $context->isBlocked = true;
            $context->status = TradeContextStatus::Skipped;
            $context->reason = "Position already exists for {$context->symbol}";
            
            Log::info("Pipeline: Skipping {$context->symbol} - Position already exists.");
            
            return $context;
        }

        return $next($context);
    }
}
