<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ExchangeProvider;
use App\Models\ExchangeAccount;
use App\Models\User;
use App\Services\Exchange\ExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checks_bybit_connection_successfully(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'testnet' => false,
                'api_key' => 'test-key',
                'api_secret' => 'test-secret',
            ]);

        Http::fake([
            'https://api.bybit.com/v5/user/query-api*' => Http::response([
                'retCode' => 0,
                'retMsg' => 'OK',
                'result' => [
                    'apiKey' => 'masked',
                ],
            ], 200),
        ]);

        $result = app(ExchangeService::class)->checkConnection($account);

        $this->assertTrue($result['connected']);
        $this->assertSame(200, $result['status']);
    }

    public function test_returns_failed_state_on_bybit_error(): void
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::factory()
            ->for($user)
            ->create([
                'exchange' => ExchangeProvider::Bybit->value,
                'testnet' => true,
                'api_key' => 'test-key',
                'api_secret' => 'test-secret',
            ]);

        Http::fake([
            'https://api-testnet.bybit.com/v5/user/query-api*' => Http::response([
                'retCode' => 10001,
                'retMsg' => 'invalid api key',
            ], 200),
        ]);

        $result = app(ExchangeService::class)->checkConnection($account);

        $this->assertFalse($result['connected']);
        $this->assertSame('invalid api key', $result['message']);
    }
}
