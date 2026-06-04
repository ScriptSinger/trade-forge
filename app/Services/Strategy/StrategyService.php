<?php

namespace App\Services\Strategy;

use App\Enums\StrategyType;
use App\Enums\TradeSignal;
use App\Models\Bot;

class StrategyService
{
    /**
     * Execute the bot's strategy and return a trade signal.
     */
    public function execute(Bot $bot, array $candles): TradeSignal
    {
        $strategy = $bot->strategy;

        if (!$strategy || !$strategy->is_active) {
            return TradeSignal::Hold;
        }

        $settings = $strategy->settings ?? [];


        return match ($strategy->type) {
            StrategyType::Trend => $this->trendStrategy($candles, $settings),
            StrategyType::Breakout => $this->breakoutStrategy($candles, $settings),
            StrategyType::Hybrid => $this->hybridStrategy($candles, $settings),
            default => TradeSignal::Hold,
        };
    }

    /**
     * Trend following strategy (e.g., Price vs SMA).
     */
    private function trendStrategy(array $candles, array $settings): TradeSignal
    {
        $period = $settings['period'] ?? 20;

        if (count($candles) < $period) {
            return TradeSignal::Hold;
        }

        $closePrices = array_map(fn($c) => (float) $c[4], array_slice($candles, 0, $period));
        $currentPrice = $closePrices[0];
        $sma = array_sum($closePrices) / count($closePrices);

        if ($currentPrice > $sma) {
            return TradeSignal::Buy;
        }

        if ($currentPrice < $sma) {
            return TradeSignal::Sell;
        }

        return TradeSignal::Hold;
    }

    /**
     * Breakout strategy (e.g., Price breaking High/Low of N candles).
     */
    private function breakoutStrategy(array $candles, array $settings): TradeSignal
    {
        $period = $settings['period'] ?? 10;

        if (count($candles) < $period + 1) {
            return TradeSignal::Hold;
        }

        // Exclude current candle to find previous range
        $history = array_slice($candles, 1, $period);
        $highs = array_map(fn($c) => (float) $c[2], $history);
        $lows = array_map(fn($c) => (float) $c[3], $history);

        $currentPrice = (float) $candles[0][4];
        $maxHigh = max($highs);
        $minLow = min($lows);

        if ($currentPrice > $maxHigh) {
            return TradeSignal::Buy;
        }

        if ($currentPrice < $minLow) {
            return TradeSignal::Sell;
        }

        return TradeSignal::Hold;
    }

    /**
     * Hybrid strategy combining Trend and Breakout.
     */
    private function hybridStrategy(array $candles, array $settings): TradeSignal
    {
        $trendSignal = $this->trendStrategy($candles, $settings);
        $breakoutSignal = $this->breakoutStrategy($candles, $settings);

        if ($trendSignal === $breakoutSignal) {
            return $trendSignal;
        }

        return TradeSignal::Hold;
    }
}
