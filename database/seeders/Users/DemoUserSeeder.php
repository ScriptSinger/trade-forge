<?php

declare(strict_types=1);

namespace Database\Seeders\Users;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public const EMAIL = 'test@example.com';

    public const NAME = 'Test User';

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => self::NAME,
                'password' => 'password',
            ],
        );
    }
}