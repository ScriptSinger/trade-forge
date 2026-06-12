<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use App\Enums\PositionStatus;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckGlobalLimits
{
    public function handle(TradeContext $context, Closure $next)
    {
        // 1. Лимит позиций: не более 3 одновременно открытых
        $openPositionsCount = $context->bot->positions()
            ->where('status', PositionStatus::Open)
            ->count();

        if ($openPositionsCount >= 3) {
            $context->isBlocked = true;
            $context->reason = "Max positions reached (3)";
            return $context;
        }

        return $next($context);
    }
}
