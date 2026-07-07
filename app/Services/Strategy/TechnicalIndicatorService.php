<?php

namespace App\Services\Strategy;

class TechnicalIndicatorService
{
    /**
     * Calculate EMA (Exponential Moving Average)
     */
    public function ema(array $prices, int $period): array
    {
        if (count($prices) < $period) {
            return [];
        }

        $multiplier = 2 / ($period + 1);
        $ema = [];

        // Initial SMA
        $sum = 0;
        for ($i = 0; $i < $period; $i++) {
            $sum += $prices[$i];
        }
        $ema[$period - 1] = $sum / $period;

        for ($i = $period; $i < count($prices); $i++) {
            $ema[$i] = ($prices[$i] - $ema[$i - 1]) * $multiplier + $ema[$i - 1];
        }

        return $ema;
    }

    /**
     * Calculate RSI (Relative Strength Index)
     * Ported from Python/TradingView style
     */
    public function rsi(array $prices, int $period = 14): array
    {
        if (count($prices) <= $period) {
            return [];
        }

        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($prices); $i++) {
            $diff = $prices[$i] - $prices[$i - 1];
            $gains[] = max(0, $diff);
            $losses[] = max(0, -$diff);
        }

        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        $rsi = [];
        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i]) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;

            if ($avgLoss == 0) {
                $rsi[$i + 1] = 100;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi[$i + 1] = 100 - (100 / (1 + $rs));
            }
        }

        return $rsi;
    }

    /**
     * Calculate ADX (Average Directional Index)
     */
    public function adx(array $candles, int $period = 14): array
    {
        if (count($candles) <= $period * 2) {
            return [];
        }

        $plusDm = [];
        $minusDm = [];
        $tr = [];

        for ($i = 1; $i < count($candles); $i++) {
            $upMove = $candles[$i]['high'] - $candles[$i - 1]['high'];
            $downMove = $candles[$i - 1]['low'] - $candles[$i]['low'];

            $plusDm[] = ($upMove > $downMove && $upMove > 0) ? $upMove : 0;
            $minusDm[] = ($downMove > $upMove && $downMove > 0) ? $downMove : 0;

            $tr[] = max(
                $candles[$i]['high'] - $candles[$i]['low'],
                abs($candles[$i]['high'] - $candles[$i - 1]['close']),
                abs($candles[$i]['low'] - $candles[$i - 1]['close'])
            );
        }

        $atr = $this->wildersSmoothing($tr, $period);
        $plusDiRaw = $this->wildersSmoothing($plusDm, $period);
        $minusDiRaw = $this->wildersSmoothing($minusDm, $period);

        $dx = [];
        foreach ($atr as $i => $atrVal) {
            $pDi = $atrVal > 0 ? 100 * ($plusDiRaw[$i] / $atrVal) : 0;
            $mDi = $atrVal > 0 ? 100 * ($minusDiRaw[$i] / $atrVal) : 0;

            $sum = $pDi + $mDi;
            $dx[$i] = ($sum == 0) ? 0 : 100 * abs($pDi - $mDi) / $sum;
        }

        return $this->wildersSmoothing($dx, $period);
    }

    /**
     * Calculate ATR (Average True Range)
     */
    public function atr(array $candles, int $period = 14): array
    {
        if (count($candles) < 2) {
            return [];
        }

        $tr = [];
        for ($i = 1; $i < count($candles); $i++) {
            $tr[] = max(
                $candles[$i]['high'] - $candles[$i]['low'],
                abs($candles[$i]['high'] - $candles[$i - 1]['close']),
                abs($candles[$i]['low'] - $candles[$i - 1]['close'])
            );
        }

        return $this->wildersSmoothing($tr, $period);
    }

    private function wildersSmoothing(array $data, int $period): array
    {
        if (count($data) < $period) {
            return [];
        }

        $smoothed = [];
        $currentSum = array_sum(array_slice($data, 0, $period));
        $smoothed[$period - 1] = $currentSum / $period;

        for ($i = $period; $i < count($data); $i++) {
            $smoothed[$i] = ($smoothed[$i - 1] * ($period - 1) + $data[$i]) / $period;
        }

        return $smoothed;
    }
}
