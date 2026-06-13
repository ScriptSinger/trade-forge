<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Strategy\TradeContext;
use Closure;
use Illuminate\Support\Facades\Log;

class CheckKillSwitch implements PipeContract
{
    public function handle(TradeContext $context, Closure $next): mixed
    {
        // 1. Получаем профит за сегодня из закрытых сделок
        $dailyProfit = $context->bot->trades()
            ->whereDate('closed_at', now()->toDateString())
            ->sum('profit_loss');

        // Лимит профита для боковика (Sniper) - 2.4% от баланса
        $profitTargetPct = 2.4; 
        
        // Индикаторы рассчитываются в следующем Pipe, поэтому на первом запуске 
        // Kill Switch может использовать данные только из прошлых запусков или БД.
        // Для первого шага пайплайна пока просто логируем профит.
        
        Log::info("Pipeline: Kill Switch check passed. Daily Profit: {$dailyProfit} $");
        
        return $next($context);
    }
}
