<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExchangeAccount;
use Illuminate\Console\Command;

class SyncExchangeCredentialsCommand extends Command
{
    protected $signature = 'exchange:sync-credentials
                            {--account= : Exchange account ID (default: first account)}
                            {--force : Overwrite even if credentials decrypt successfully}';

    protected $description = 'Re-encrypt Bybit API credentials from BYBIT_API_KEY / BYBIT_API_SECRET in .env';

    public function handle(): int
    {
        $apiKey = env('BYBIT_API_KEY');
        $apiSecret = env('BYBIT_API_SECRET');

        if (!filled($apiKey) || !filled($apiSecret)) {
            $this->error('Set BYBIT_API_KEY and BYBIT_API_SECRET in .env first.');

            return self::FAILURE;
        }

        $accountId = $this->option('account');

        $account = $accountId !== null
            ? ExchangeAccount::query()->find($accountId)
            : ExchangeAccount::query()->orderBy('id')->first();

        if ($account === null) {
            $this->error('Exchange account not found.');

            return self::FAILURE;
        }

        if (!$this->option('force') && filled($account->api_key) && filled($account->api_secret)) {
            $this->info("Account #{$account->id} already has decryptable credentials. Use --force to overwrite.");

            return self::SUCCESS;
        }

        $account->api_key = $apiKey;
        $account->api_secret = $apiSecret;
        $account->save();

        $this->info("Credentials re-encrypted for exchange account #{$account->id} ({$account->name}).");

        return self::SUCCESS;
    }
}