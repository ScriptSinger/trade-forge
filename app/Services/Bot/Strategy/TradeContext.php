<?php

namespace App\Services\Bot\Strategy;

use App\Enums\TradeContextStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;

class TradeContext
{
    public function __construct(
        public Bot $bot,
        public string $symbol,

        public array $candles = [],
        public string $entryInterval = '15',

        public array $indicators = [],
        public TradeSignal $signal = TradeSignal::Hold,
        public float $stopLoss = 0,
        public float $takeProfit = 0,
        /** Base-asset quantity (coins), not USDT. */
        public float $quantity = 0,
        public string $mode = 'Sniper',
        public bool $isBlocked = false,
        public string $reason = '',
        public string $blockedBy = '',
        public TradeContextStatus $status = TradeContextStatus::Pending,
    ) {}

    public function lastCandle(): ?array
    {
        return ! empty($this->candles) ? end($this->candles) : null;
    }

    public function prevCandle(): ?array
    {
        $count = count($this->candles);

        return $count >= 2 ? $this->candles[$count - 2] : null;
    }

    public function botId(): int
    {
        return $this->bot->id;
    }
}
