<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckAdxStrength implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
    {
        if (! ($context->bot->strategy->settings['enable_adx'] ?? true)) {
            return $next($context);
        }

        $adxArr = $context->indicators['adx'] ?? [];
        $adx = end($adxArr) ?: 0;
        $minAdx = $context->bot->strategy->settings['min_adx'] ?? 20;

        if ($adx <= $minAdx) {
            $context->isBlocked = true;
            $context->reason = "Low ADX ({$adx} <= {$minAdx})";
            return $context;
        }

        return $next($context);
    }
}
