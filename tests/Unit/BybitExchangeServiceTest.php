<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ExchangeProvider;
use App\Models\ExchangeAccount;
use App\Models\User;
use App\Services\Exchange\BybitExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BybitExchangeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_klines_sends_limit_from_strategy_default(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'api_url' => 'https://api.bybit.com',
            ]);

        Http::fake([
            'https://api.bybit.com/v5/market/kline*' => Http::response([
                'retCode' => 0,
                'result' => ['list' => []],
            ], 200),
        ]);

        app(BybitExchangeService::class)->getKlines($account, 'BTCUSDT', '15');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/v5/market/kline')
                && (int) ($request->data()['limit'] ?? 0) === BybitExchangeService::DEFAULT_KLINE_LIMIT;
        });
    }

    public function test_get_klines_accepts_custom_limit(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'api_url' => 'https://api.bybit.com',
            ]);

        Http::fake([
            'https://api.bybit.com/v5/market/kline*' => Http::response([
                'retCode' => 0,
                'result' => ['list' => []],
            ], 200),
        ]);

        app(BybitExchangeService::class)->getKlines($account, 'ETHUSDT', '60', 500);

        Http::assertSent(function ($request): bool {
            return (int) ($request->data()['limit'] ?? 0) === 500;
        });
    }

    public function test_get_coin_free_balance_reads_available_balance_from_wallet(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'api_url' => 'https://api.bybit.com',
            ]);

        Http::fake([
            'https://api.bybit.com/v5/account/wallet-balance*' => Http::response([
                'retCode' => 0,
                'result' => [
                    'list' => [
                        [
                            'coin' => [
                                [
                                    'coin' => 'ETH',
                                    'availableBalance' => '1.2345',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $balance = app(BybitExchangeService::class)->getCoinFreeBalance($account, 'ETH');

        $this->assertEqualsWithDelta(1.2345, $balance, 0.0001);
    }

    public function test_get_coin_free_balance_ignores_empty_available_to_withdraw_on_unified(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'api_url' => 'https://api.bybit.com',
            ]);

        Http::fake([
            'https://api.bybit.com/v5/account/wallet-balance*' => Http::response([
                'retCode' => 0,
                'result' => [
                    'list' => [
                        [
                            'coin' => [
                                [
                                    'coin' => 'USDT',
                                    'availableToWithdraw' => '',
                                    'walletBalance' => '23.0562',
                                    'locked' => '0',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $balance = app(BybitExchangeService::class)->getCoinFreeBalance($account, 'USDT');

        $this->assertEqualsWithDelta(23.0562, $balance, 0.0001);
    }

    public function test_get_coin_free_balance_subtracts_locked_spot_orders(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'api_url' => 'https://api.bybit.com',
            ]);

        Http::fake([
            'https://api.bybit.com/v5/account/wallet-balance*' => Http::response([
                'retCode' => 0,
                'result' => [
                    'list' => [
                        [
                            'coin' => [
                                [
                                    'coin' => 'USDT',
                                    'availableToWithdraw' => '0',
                                    'walletBalance' => '23.0562',
                                    'locked' => '3.5',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $balance = app(BybitExchangeService::class)->getCoinFreeBalance($account, 'USDT');

        $this->assertEqualsWithDelta(19.5562, $balance, 0.0001);
    }
}
