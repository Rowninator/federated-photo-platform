<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_log_in_with_a_normalized_email(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.test',
            'password' => Hash::make('login-password'),
        ]);

        $this->startSession();
        $originalSessionId = session()->getId();

        $this->postJson('/login', [
            'email' => 'ALICE@EXAMPLE.TEST',
            'password' => 'login-password',
        ])->assertOk()->assertExactJson(['status' => 'ok']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($originalSessionId, session()->getId());
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'alice@example.test',
            'password' => Hash::make('login-password'),
        ]);

        $this->postJson('/login', [
            'email' => 'alice@example.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertExactJson([
            'message' => 'Invalid credentials.',
        ]);

        $this->assertGuest();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/logout')->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_logout_clears_authentication_and_invalidates_the_session(): void
    {
        $user = User::factory()->create();

        $this->withSession([
            '_token' => 'old-csrf-token',
            'logout_marker' => 'present',
        ])->actingAs($user);

        $this->postJson('/logout')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertSessionMissing('logout_marker');

        $this->assertGuest();
        $this->assertNotSame('old-csrf-token', session()->token());
    }
}
