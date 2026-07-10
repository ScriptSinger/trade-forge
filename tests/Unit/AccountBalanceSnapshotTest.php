<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Exchange\Balance\AccountBalanceSnapshot;
use PHPUnit\Framework\TestCase;

class AccountBalanceSnapshotTest extends TestCase
{
    public function test_uses_available_balance_when_present(): void
    {
        $snapshot = AccountBalanceSnapshot::fromCoinData('USDT', [
            'walletBalance' => '23.0562',
            'availableBalance' => '20.5',
            'locked' => '1.0',
        ]);

        $this->assertSame('USDT', $snapshot->coin);
        $this->assertEqualsWithDelta(23.0562, $snapshot->wallet, 0.0001);
        $this->assertEqualsWithDelta(20.5, $snapshot->free, 0.0001);
        $this->assertEqualsWithDelta(1.0, $snapshot->locked, 0.0001);
        $this->assertSame('availableBalance', $snapshot->freeSource);
    }

    public function test_falls_back_to_wallet_minus_locked_when_withdraw_field_empty(): void
    {
        $snapshot = AccountBalanceSnapshot::fromCoinData('USDT', [
            'availableToWithdraw' => '',
            'walletBalance' => '23.0562',
            'locked' => '0',
        ]);

        $this->assertEqualsWithDelta(23.0562, $snapshot->free, 0.0001);
        $this->assertSame('walletBalance_minus_locked', $snapshot->freeSource);
    }

    public function test_subtracts_locked_spot_balance(): void
    {
        $snapshot = AccountBalanceSnapshot::fromCoinData('USDT', [
            'availableToWithdraw' => '0',
            'walletBalance' => '23.0562',
            'locked' => '3.5',
        ]);

        $this->assertEqualsWithDelta(19.5562, $snapshot->free, 0.0001);
    }
}