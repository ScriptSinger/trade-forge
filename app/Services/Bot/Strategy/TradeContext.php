<?php

namespace App\Services\Bot\Strategy;

use App\Enums\TradeSignal;
use App\Enums\TradeContextStatus;

class TradeContext
{
    public function __construct(
        public int $botId,
        public string $symbol,
        
        public array $candles = [],
        public array $btcCandles = [],

        // Flattened configuration
        public bool $btcTrendEnabled = false,
        public int $btcEmaFast = 50,
        public int $btcEmaSlow = 200,
        public string $btcBenchmarkSymbol = 'BTCUSDT',
        public string $btcBenchmarkInterval = '15',
        public string $entryInterval = '15',

        public array $indicators = [],
        public TradeSignal $signal = TradeSignal::Hold,
        public float $stopLoss = 0,
        public float $takeProfit = 0,
        public float $quantity = 0,
        public string $mode = 'Sniper',
        public bool $isBlocked = false,
        public string $reason = '',
        public TradeContextStatus $status = TradeContextStatus::Pending,
    ) {}

    public function lastCandle(): ?array
    {
        return !empty($this->candles) ? end($this->candles) : null;
    }

    public function prevCandle(): ?array
    {
        $count = count($this->candles);
        return $count >= 2 ? $this->candles[$count - 2] : null;
    }
}
