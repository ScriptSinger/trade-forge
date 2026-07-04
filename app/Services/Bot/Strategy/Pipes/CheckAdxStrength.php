<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckAdxStrength implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
    {
        // For now, assuming ADX is always enabled if settings exist, 
        // or add an 'enable_adx' field to StrategyEntrySettings if needed.
        $entrySettings = $context->bot->strategy->entrySettings;

        $adxArr = $context->indicators['adx'] ?? [];
        $adx = end($adxArr) ?: 0;
        $minAdx = $entrySettings->adx_min ?? 20;

        if ($adx <= $minAdx) {
            $context->isBlocked = true;
            $context->reason = "Low ADX ({$adx} <= {$minAdx})";
            return $context;
        }

        return $next($context);
    }
}
