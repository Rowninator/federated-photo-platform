<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProfileUsernameTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_is_normalized_to_lowercase_before_persistence(): void
    {
        $profile = Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'Alice.Example-1',
        ]);

        $this->assertSame('alice.example-1', $profile->username);
        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'username' => 'alice.example-1',
        ]);
    }

    public function test_canonical_username_validation_accepts_allowed_characters(): void
    {
        $username = Profile::normalizeUsername('Alice_1.Example-Test');

        $validator = Validator::make(
            ['username' => $username],
            ['username' => Profile::usernameRules()],
        );

        $this->assertSame('alice_1.example-test', $username);
        $this->assertTrue($validator->passes());
    }

    public function test_canonical_username_validation_rejects_invalid_values(): void
    {
        foreach (['', str_repeat('a', 31), 'alice@example'] as $username) {
            $validator = Validator::make(
                ['username' => $username],
                ['username' => Profile::usernameRules()],
            );

            $this->assertTrue($validator->fails(), "Expected [{$username}] to be invalid.");
        }
    }

    public function test_canonical_username_validation_rejects_an_existing_username(): void
    {
        Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'alice',
        ]);

        $validator = Validator::make(
            ['username' => 'alice'],
            ['username' => Profile::usernameRules()],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('username', $validator->errors()->toArray());
    }
}
