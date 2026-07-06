<?php

declare(strict_types=1);

namespace App\Services\Notifications;

class TradeTelegramNotifier
{
    public function __construct(
        private TelegramNotifier $telegram,
    ) {}

    public function notifyEntry(
        string $symbol,
        float $price,
        float $sl,
        float $tp,
        float $costUsdt,
    ): void {
        if (!$this->telegram->isConfigured()) {
            return;
        }

        $message = implode("\n", [
            '🟢 <b>ВХОД: ' . e($symbol) . '</b>',
            'Цена: ' . $this->formatPrice($price),
            'SL: ' . $this->formatPrice($sl),
            'TP: ' . $this->formatPrice($tp),
            'Объем: ' . $this->formatUsdt($costUsdt) . '$',
        ]);

        $this->telegram->send($message);
    }

    public function notifySurferActivation(string $symbol): void
    {
        if (!$this->telegram->isConfigured()) {
            return;
        }

        $this->telegram->send('🏄 <b>' . e($symbol) . '</b>: Ракета! Трейлинг активирован.');
    }

    public function notifyExit(
        string $symbol,
        string $reason,
        float $portion,
        float $pnlPct,
        float $profitUsdt,
    ): void {
        if (!$this->telegram->isConfigured()) {
            return;
        }

        $emoji = $profitUsdt > 0
            ? '✅'
            : ($portion < 1.0 ? '➖' : '❌');

        $portionPct = (int) round($portion * 100);

        $message = implode("\n", [
            $emoji . ' <b>ВЫХОД: ' . e($symbol) . '</b>',
            'Причина: ' . e($reason) . " ({$portionPct}%)",
            'ЧИСТЫЙ PnL: ' . $this->formatSigned($pnlPct) . '% ('
                . $this->formatSigned($profitUsdt) . '$)',
        ]);

        $this->telegram->send($message);
    }

    private function formatPrice(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }

    private function formatUsdt(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function formatSigned(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}