<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Casts\ResilientEncrypted;
use App\Models\ExchangeAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResilientEncryptedCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_ciphertext_is_invalid(): void
    {
        $account = ExchangeAccount::factory()->create([
            'api_key' => 'valid-key',
            'api_secret' => 'valid-secret',
        ]);

        ExchangeAccount::query()
            ->whereKey($account->id)
            ->update(['api_key' => 'eyJpdiI6ImludmFsaWQifQ==']);

        $fresh = ExchangeAccount::query()->findOrFail($account->id);

        $this->assertNull($fresh->api_key);
        $this->assertSame('valid-secret', $fresh->api_secret);
    }

    public function test_preserves_existing_ciphertext_when_setting_empty_value(): void
    {
        $cast = new ResilientEncrypted;
        $account = new ExchangeAccount;
        $encrypted = encrypt('keep-me');

        $result = $cast->set($account, 'api_key', '', ['api_key' => $encrypted]);

        $this->assertSame($encrypted, $result);
    }
}