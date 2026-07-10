<?php

declare(strict_types=1);

namespace App\Services\Exchange\Balance;

readonly class AccountBalanceQuery
{
    public function __construct(
        public ?AccountBalanceSnapshot $snapshot,
        public int $retCode,
        public string $retMsg,
    ) {}

    public function ok(): bool
    {
        return $this->retCode === 0 && $this->snapshot !== null;
    }
}