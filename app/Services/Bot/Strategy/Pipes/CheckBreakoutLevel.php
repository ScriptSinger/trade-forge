<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckBreakoutLevel implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
    {
        if (! ($context->bot->strategy->settings['enable_breakout'] ?? true)) {
            return $next($context);
        }

        $lastCandle = $context->lastCandle();
        $resistance = $context->indicators['resistance'] ?? 0;
        $currentClose = $lastCandle['close'] ?? 0;

        if ($currentClose <= $resistance) {
            $context->isBlocked = true;
            $context->reason = "No breakout (Price:{$currentClose} <= Resistance:{$resistance})";
            return $context;
        }

        return $next($context);
    }
}
