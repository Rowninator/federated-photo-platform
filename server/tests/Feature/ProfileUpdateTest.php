<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_update_profile_presentation_fields(): void
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'username' => 'alice',
        ]);

        $this->actingAs($user)->patchJson('/profile', [
            'display_name' => 'Alice Example',
            'bio' => 'Local photographer',
        ])->assertOk()->assertExactJson([
            'username' => 'alice',
            'display_name' => 'Alice Example',
            'bio' => 'Local photographer',
        ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'username' => 'alice',
            'display_name' => 'Alice Example',
            'bio' => 'Local photographer',
        ]);
    }

    public function test_a_guest_cannot_update_a_profile(): void
    {
        $this->patchJson('/profile', [
            'display_name' => 'Alice Example',
        ])->assertUnauthorized();
    }

    public function test_only_the_authenticated_users_allowed_profile_fields_are_updated(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.test']);
        $profile = Profile::create([
            'user_id' => $user->id,
            'username' => 'alice',
            'display_name' => 'Alice',
        ]);
        $originalPassword = $user->password;

        $otherUser = User::factory()->create(['email' => 'bob@example.test']);
        $otherProfile = Profile::create([
            'user_id' => $otherUser->id,
            'username' => 'bob',
            'display_name' => 'Bob',
        ]);

        $this->actingAs($user)->patchJson('/profile', [
            'display_name' => 'Updated Alice',
            'username' => 'changed',
            'user_id' => $otherUser->id,
            'email' => 'changed@example.test',
            'password' => 'changed-password',
        ])->assertOk()->assertExactJson([
            'username' => 'alice',
            'display_name' => 'Updated Alice',
            'bio' => null,
        ]);

        $this->assertSame('alice', $profile->fresh()->username);
        $this->assertSame($user->id, $profile->fresh()->user_id);
        $this->assertSame('alice@example.test', $user->fresh()->email);
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertSame('Bob', $otherProfile->fresh()->display_name);
    }

    public function test_empty_presentation_fields_are_stored_as_null(): void
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'username' => 'alice',
            'display_name' => 'Alice',
            'bio' => 'Local photographer',
        ]);

        $this->actingAs($user)->patchJson('/profile', [
            'display_name' => '',
            'bio' => '',
        ])->assertOk()->assertExactJson([
            'username' => 'alice',
            'display_name' => null,
            'bio' => null,
        ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'display_name' => null,
            'bio' => null,
        ]);
    }

    public function test_invalid_presentation_fields_are_rejected(): void
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'username' => 'alice',
            'display_name' => 'Alice',
            'bio' => 'Local photographer',
        ]);

        $this->actingAs($user)->patchJson('/profile', [
            'display_name' => str_repeat('a', 101),
            'bio' => str_repeat('b', 501),
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'display_name',
            'bio',
        ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'display_name' => 'Alice',
            'bio' => 'Local photographer',
        ]);
    }
}
