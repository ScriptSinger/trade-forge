<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;

class CheckEmaTrend implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
    {
        if (! ($context->bot->strategy->settings['enable_ema'] ?? true)) {
            return $next($context);
        }

        $emaFastArr = $context->indicators['ema_fast'] ?? $context->indicators['ema50'] ?? [];
        $emaSlowArr = $context->indicators['ema_slow'] ?? $context->indicators['ema200'] ?? [];
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
