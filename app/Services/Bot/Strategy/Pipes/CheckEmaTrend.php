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

        $ema50Arr = $context->indicators['ema50'] ?? [];
        $ema200Arr = $context->indicators['ema200'] ?? [];
        $ema50 = end($ema50Arr) ?: 0;
        $ema200 = end($ema200Arr) ?: 0;
        
        if ($ema50 <= $ema200) {
            $context->isBlocked = true;
            $context->reason = "Bearish EMA alignment (Fast:{$ema50} <= Slow:{$ema200})";
            return $context;
        }
        
        return $next($context);
    }
}
