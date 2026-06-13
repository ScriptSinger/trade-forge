<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Enums\PositionStatus;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckGlobalLimits implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
    {
        $maxPositions = $context->bot->max_open_positions ?? 3;

        // 1. Лимит позиций
        $openPositionsCount = $context->bot->positions()
            ->where('status', PositionStatus::Open)
            ->count();

        if ($openPositionsCount >= $maxPositions) {
            $context->isBlocked = true;
            $context->reason = "Max positions reached ({$maxPositions})";
            return $context;
        }

        return $next($context);
    }
}
