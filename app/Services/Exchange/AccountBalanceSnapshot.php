<?php

declare(strict_types=1);

namespace App\Services\Exchange;

readonly class AccountBalanceSnapshot
{
    public function __construct(
        public string $coin,
        public float $wallet,
        public float $free,
        public float $locked,
        public string $freeSource,
    ) {}

    public static function fromCoinData(string $coin, array $coinData): self
    {
        $wallet = self::parseAmount($coinData['walletBalance'] ?? $coinData['equity'] ?? null) ?? 0.0;
        $locked = (float) ($coinData['locked'] ?? 0);

        foreach (['availableBalance', 'free'] as $field) {
            $amount = self::parseAmount($coinData[$field] ?? null);

            if ($amount !== null) {
                return new self(
                    coin: $coin,
                    wallet: $wallet,
                    free: $amount,
                    locked: $locked,
                    freeSource: $field,
                );
            }
        }

        return new self(
            coin: $coin,
            wallet: $wallet,
            free: max(0.0, $wallet - $locked),
            locked: $locked,
            freeSource: 'walletBalance_minus_locked',
        );
    }

    public function isPresent(): bool
    {
        return $this->wallet > 0 || $this->free > 0 || $this->locked > 0;
    }

    /**
     * @return array<string, float|string>
     */
    public function toArray(): array
    {
        return [
            'coin' => $this->coin,
            'wallet' => $this->wallet,
            'free' => $this->free,
            'locked' => $this->locked,
            'free_source' => $this->freeSource,
        ];
    }

    private static function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $amount = (float) $value;

        return $amount > 0 ? $amount : null;
    }
}