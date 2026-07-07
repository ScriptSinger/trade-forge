<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Enums\PositionStatus;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckGlobalLimits implements PipeContract
{
    use BlocksTradeContext;

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $risk = $context->bot->strategy->riskSettings;
        $maxPositions = (int) ($risk?->max_positions ?? 3);

        $openPositionsCount = $context->bot->positions()
            ->where('status', PositionStatus::Open)
            ->count();

        if ($openPositionsCount >= $maxPositions) {
            return $this->block($context, "Max positions reached ({$maxPositions})");
        }

        return $next($context);
    }
}
