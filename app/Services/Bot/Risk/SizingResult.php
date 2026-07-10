<?php

declare(strict_types=1);

namespace App\Services\Bot\Risk;

readonly class SizingResult
{
    public const REASON_OK = 'ok';

    public const REASON_INVALID_PRICES = 'invalid_prices';

    public const REASON_WALLET_EMPTY = 'wallet_empty';

    public const REASON_INVALID_PRICE_RISK = 'invalid_price_risk';

    public const REASON_BELOW_MIN_ORDER = 'below_min_order';

    public const REASON_BELOW_MIN_AFTER_NORMALIZE = 'below_min_after_normalize';

    /**
     * @param  array<string, float|int|string|null>  $debug
     */
    public function __construct(
        public ?float $quantity,
        public string $reason,
        public array $debug = [],
    ) {}

    public function ok(): bool
    {
        return $this->reason === self::REASON_OK
            && $this->quantity !== null
            && $this->quantity > 0;
    }
}