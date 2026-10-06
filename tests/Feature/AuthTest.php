<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function tokenCookie(TestResponse $response): string
    {
        $cookie = $response->getCookie(config('jwt.cookie_key_name'), decrypt: false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());

        return $cookie->getValue();
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['expires_in', 'user' => ['id', 'name', 'email']])
            ->assertJsonMissingPath('access_token');

        $this->tokenCookie($response);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_login_sets_http_only_cookie_that_authenticates_requests(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonMissingPath('access_token');

        $token = $this->tokenCookie($response);

        $this->withUnencryptedCookie(config('jwt.cookie_key_name'), $token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_protected_route_requires_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_protected_route_returns_401_without_json_accept_header(): void
    {
        $this->get('/api/auth/me')->assertUnauthorized();
    }

    public function test_logout_invalidates_token_and_clears_cookie(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $response = $this->withUnencryptedCookie(config('jwt.cookie_key_name'), $token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertTrue($response->getCookie(config('jwt.cookie_key_name'), decrypt: false)->isCleared());

        auth('api')->forgetUser();

        $this->withUnencryptedCookie(config('jwt.cookie_key_name'), $token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_token_can_be_refreshed(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $response = $this->withUnencryptedCookie(config('jwt.cookie_key_name'), $token)
            ->postJson('/api/auth/refresh')
            ->assertOk();

        $this->assertNotSame($token, $this->tokenCookie($response));
    }
}
