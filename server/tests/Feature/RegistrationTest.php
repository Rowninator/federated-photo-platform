<?php

namespace Tests\Feature;

use App\Actions\RegisterUser;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_local_account_can_be_registered_and_authenticated(): void
    {
        $response = $this->postJson('/register', [
            'username' => 'Alice.Example-1',
            'email' => 'Alice@Example.TEST',
            'password' => 'registration-password',
            'password_confirmation' => 'registration-password',
        ]);

        $response
            ->assertCreated()
            ->assertExactJson(['status' => 'ok']);

        $user = User::where('email', 'alice@example.test')->firstOrFail();

        $this->assertSame('alice.example-1', $user->profile->username);
        $this->assertNotSame('registration-password', $user->password);
        $this->assertTrue(Hash::check('registration-password', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('profiles', 1);
    }

    public function test_a_duplicate_email_is_rejected_after_normalization(): void
    {
        User::factory()->create(['email' => 'alice@example.test']);

        $this->postJson('/register', [
            'username' => 'alice',
            'email' => 'ALICE@EXAMPLE.TEST',
            'password' => 'registration-password',
            'password_confirmation' => 'registration-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_a_duplicate_username_is_rejected_after_normalization(): void
    {
        Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'alice',
        ]);

        $this->postJson('/register', [
            'username' => 'ALICE',
            'email' => 'another@example.test',
            'password' => 'registration-password',
            'password_confirmation' => 'registration-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('profiles', 1);
    }

    public function test_password_confirmation_is_required_to_match(): void
    {
        $this->postJson('/register', [
            'username' => 'alice',
            'email' => 'alice@example.test',
            'password' => 'registration-password',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('profiles', 0);
        $this->assertGuest();
    }

    public function test_profile_failure_rolls_back_user_creation(): void
    {
        Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'alice',
        ]);

        try {
            app(RegisterUser::class)([
                'username' => 'alice',
                'email' => 'another@example.test',
                'password' => 'registration-password',
            ]);

            $this->fail('Expected duplicate profile username to fail.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('users', ['email' => 'another@example.test']);
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('profiles', 1);
        }
    }
}
