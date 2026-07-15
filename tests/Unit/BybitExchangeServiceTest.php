<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ExchangeProvider;
use App\Models\ExchangeAccount;
use App\Models\User;
use App\Services\Exchange\Bybit\BybitExchangeService;
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

    public function test_get_account_balance_reads_available_balance_from_wallet(): void
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

        $snapshot = app(BybitExchangeService::class)->getAccountBalance($account, 'ETH');

        $this->assertNotNull($snapshot);
        $this->assertEqualsWithDelta(1.2345, $snapshot->free, 0.0001);
    }

    public function test_get_account_balance_ignores_empty_available_to_withdraw_on_unified(): void
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

        $snapshot = app(BybitExchangeService::class)->getAccountBalance($account, 'USDT');

        $this->assertNotNull($snapshot);
        $this->assertEqualsWithDelta(23.0562, $snapshot->free, 0.0001);
    }

    public function test_get_account_balance_subtracts_locked_spot_orders(): void
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

        $snapshot = app(BybitExchangeService::class)->getAccountBalance($account, 'USDT');

        $this->assertNotNull($snapshot);
        $this->assertEqualsWithDelta(19.5562, $snapshot->free, 0.0001);
    }

    public function test_query_account_balance_returns_snapshot_with_wallet_and_free(): void
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
                'retMsg' => 'OK',
                'result' => [
                    'list' => [
                        [
                            'coin' => [
                                [
                                    'coin' => 'USDT',
                                    'walletBalance' => '23.0562',
                                    'locked' => '0',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $query = app(BybitExchangeService::class)->queryAccountBalance($account, 'USDT');

        $this->assertTrue($query->ok());
        $this->assertNotNull($query->snapshot);
        $this->assertEqualsWithDelta(23.0562, $query->snapshot->wallet, 0.0001);
        $this->assertEqualsWithDelta(23.0562, $query->snapshot->free, 0.0001);
    }

    public function test_normalize_quantity_uses_base_precision_when_qty_step_missing(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'api_url' => 'https://api.bybit.com',
            ]);

        Http::fake([
            'https://api.bybit.com/v5/market/instruments-info*' => Http::response([
                'retCode' => 0,
                'result' => [
                    'list' => [
                        [
                            'lotSizeFilter' => [
                                'basePrecision' => '0.01',
                                'minOrderQty' => '0.01',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $normalized = app(BybitExchangeService::class)
            ->normalizeQuantity($account, 'USD1USDT', 6.916168383161684);

        $this->assertEqualsWithDelta(6.91, $normalized, 0.00001);
    }

    public function test_place_market_order_formats_qty_to_base_precision(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'api_url' => 'https://api.bybit.com',
            ]);

        Http::fake([
            'https://api.bybit.com/v5/market/instruments-info*' => Http::response([
                'retCode' => 0,
                'result' => [
                    'list' => [
                        [
                            'lotSizeFilter' => [
                                'basePrecision' => '0.01',
                                'minOrderQty' => '0.01',
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://api.bybit.com/v5/order/create' => Http::response([
                'retCode' => 0,
                'retMsg' => 'OK',
                'result' => ['orderId' => 'test-order'],
            ], 200),
        ]);

        app(BybitExchangeService::class)->placeMarketOrder(
            $account,
            'USD1USDT',
            'buy',
            6.916168383161684,
        );

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/v5/order/create')) {
                return false;
            }

            $payload = $request->data();

            return ($payload['symbol'] ?? '') === 'USD1USDT'
                && ($payload['side'] ?? '') === 'Buy'
                && ($payload['marketUnit'] ?? '') === 'baseCoin'
                && ($payload['qty'] ?? '') === '6.91';
        });
    }

    public function test_place_market_sell_uses_base_coin_market_unit(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'api_url' => 'https://api.bybit.com',
            ]);

        Http::fake([
            'https://api.bybit.com/v5/market/instruments-info*' => Http::response([
                'retCode' => 0,
                'result' => [
                    'list' => [
                        [
                            'lotSizeFilter' => [
                                'basePrecision' => '0.01',
                                'minOrderQty' => '0.01',
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://api.bybit.com/v5/order/create' => Http::response([
                'retCode' => 0,
                'retMsg' => 'OK',
                'result' => ['orderId' => 'test-sell-order'],
            ], 200),
        ]);

        app(BybitExchangeService::class)->placeMarketOrder(
            $account,
            'LITUSDT',
            'sell',
            2.80523178,
        );

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/v5/order/create')) {
                return false;
            }

            $payload = $request->data();

            return ($payload['symbol'] ?? '') === 'LITUSDT'
                && ($payload['side'] ?? '') === 'Sell'
                && ($payload['marketUnit'] ?? '') === 'baseCoin'
                && ($payload['qty'] ?? '') === '2.8';
        });
    }
}
