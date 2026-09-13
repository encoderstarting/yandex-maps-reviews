<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_user_is_created_without_factory_and_repeated_seed_is_idempotent(): void
    {
        config()->set('demo_user', [
            'name' => 'Демо-пользователь',
            'email' => 'demo@example.test',
            'password' => 'secret-password',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'demo@example.test')->sole();

        $this->assertSame('Демо-пользователь', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertDatabaseCount('users', 1);
    }
}
