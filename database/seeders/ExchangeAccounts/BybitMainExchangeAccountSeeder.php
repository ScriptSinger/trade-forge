<?php

declare(strict_types=1);

namespace Database\Seeders\ExchangeAccounts;

use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use App\Models\ExchangeAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Users\DemoUserSeeder;
use Illuminate\Database\Seeder;

class BybitMainExchangeAccountSeeder extends Seeder
{
    public const ACCOUNT_NAME = 'Bybit Main';

    public function run(): void
    {
        $user = User::query()
            ->where('email', DemoUserSeeder::EMAIL)
            ->firstOrFail();

        ExchangeAccount::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange' => ExchangeProvider::Bybit->value,
                'name' => self::ACCOUNT_NAME,
            ],
            [
                'api_key' => env('BYBIT_API_KEY', 'demo-bybit-api-key'),
                'api_secret' => env('BYBIT_API_SECRET', 'demo-bybit-api-secret'),
                'api_url' => env('BYBIT_BASE_URL', 'https://api-testnet.bybit.com'),
                'status' => ExchangeAccountStatus::Active->value,
                'last_checked_at' => CarbonImmutable::parse('2026-06-01 12:00:00', 'UTC'),
            ],
        );
    }
}