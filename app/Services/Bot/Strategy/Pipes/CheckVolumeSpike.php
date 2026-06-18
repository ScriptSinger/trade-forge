<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckVolumeSpike implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
    {
        if (! ($context->bot->strategy->settings['enable_volume_check'] ?? true)) {
            return $next($context);
        }

        $lastCandle = $context->lastCandle();
        $avgVol = $context->indicators['avg_volume'] ?? 0;
        $currentVol = $lastCandle['vol'] ?? 0;

        if ($currentVol <= $avgVol) {
            $context->isBlocked = true;
            $context->reason = "Low volume (Vol:{$currentVol} <= Avg:{$avgVol})";
            return $context;
        }

        return $next($context);
    }
}
