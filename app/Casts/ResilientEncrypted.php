<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * @implements CastsAttributes<string|null, string|null>
 */
final class ResilientEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return decrypt($value);
        } catch (DecryptException $e) {
            Log::warning('Encrypted attribute cannot be decrypted (APP_KEY mismatch?)', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'attribute' => $key,
            ]);

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $attributes[$key] ?? null;
        }

        return encrypt($value);
    }
}