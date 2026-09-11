<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_create_session_and_return_user(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'demo@example.com');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_return_422_and_do_not_create_session(): void
    {
        User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'demo@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Неверный email или пароль.');
        $this->assertGuest();
    }

    public function test_unauthenticated_user_endpoint_returns_401(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/login', [
            'email' => 'demo@example.com',
            'password' => 'password',
        ])->assertOk();

        $response = $this->deleteJson('/logout');

        $response->assertNoContent();
        $this->assertGuest('web');
    }
}
