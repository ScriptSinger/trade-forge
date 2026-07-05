<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Enums\StrategyType;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckBreakoutLevel implements PipeContract
{
    use BlocksTradeContext;

    public function handle(TradeContext $context, Closure $next): mixed
    {
        if ($this->shouldSkip($context)) {
            return $next($context);
        }

        $lastCandle = $context->lastCandle();
        $prevResistance = $context->indicators['prev_resistance'] ?? 0;
        $currentClose = $lastCandle['close'] ?? 0;

        if ($currentClose <= $prevResistance) {
            return $this->block($context, "No breakout (Price:{$currentClose} <= PrevResistance:{$prevResistance})");
        }

        return $next($context);
    }

    private function shouldSkip(TradeContext $context): bool
    {
        return $context->bot->strategy->type === StrategyType::Trend;
    }
}