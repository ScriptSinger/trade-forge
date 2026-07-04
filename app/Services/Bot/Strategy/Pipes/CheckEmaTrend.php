<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckEmaTrend implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
    {
        $emaFastArr = $context->indicators['ema_fast'] ?? [];
        $emaSlowArr = $context->indicators['ema_slow'] ?? [];
        $emaFast = end($emaFastArr) ?: 0;
        $emaSlow = end($emaSlowArr) ?: 0;
        
        if ($emaFast <= $emaSlow) {
            $context->isBlocked = true;
            $context->reason = "Bearish EMA alignment (Fast:{$emaFast} <= Slow:{$emaSlow})";
            return $context;
        }
        
        return $next($context);
    }
}
