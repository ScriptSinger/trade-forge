<?php

namespace App\Services\Bot\Strategy\Pipes;

use App\Services\Bot\Concerns\ResolvesTradingLogger;
use App\Services\Bot\PositionSizingService;
use App\Services\Bot\Strategy\Pipes\Concerns\BlocksTradeContext;
use App\Services\Bot\Strategy\TradeContext;
use Closure;

class ApplyRiskManagement implements PipeContract
{
    use BlocksTradeContext;
    use ResolvesTradingLogger;

    public function __construct(
        private PositionSizingService $sizing,
    ) {}

    public function handle(TradeContext $context, Closure $next): mixed
    {
        $lastCandle = $context->lastCandle();
        $entryPrice = (float) ($lastCandle['close'] ?? 0);

        if ($entryPrice <= 0) {
            return $this->block($context, 'Invalid entry price');
        }

        $atrArr = $context->indicators['atr'] ?? [];
        $atr = end($atrArr) ?: 0;

        if ($atr <= 0) {
            return $this->block($context, 'Invalid ATR value');
        }

        $riskSettings = $context->bot->strategy->riskSettings;
        $slMult = (float) ($riskSettings->sl_multiplier ?? 2.0);
        $tpMult = (float) ($riskSettings->tp_multiplier ?? 3.0);

        $context->stopLoss = $entryPrice - ($atr * $slMult);
        $context->takeProfit = $entryPrice + ($atr * $tpMult);

        if ($context->stopLoss >= $entryPrice) {
            return $this->block($context, 'Invalid price risk calculation');
        }

        $quantity = $this->sizing->calculateQuantity(
            $context->bot,
            $context->symbol,
            $entryPrice,
            $context->stopLoss,
        );

        if ($quantity === null || $quantity <= 0) {
            return $this->block($context, 'Order size below minimum or insufficient balance');
        }

        $context->quantity = $quantity;

        $this->tradingLog()->riskDebug('Risk applied', [
            'symbol' => $context->symbol,
            'sl' => $context->stopLoss,
            'tp' => $context->takeProfit,
            'qty' => $context->quantity,
        ]);

        return $next($context);
    }
}
