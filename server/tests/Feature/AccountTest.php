<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_retrieve_their_account_and_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.test',
            'password' => 'secret-password',
            'remember_token' => 'secret-token',
        ]);

        Profile::create([
            'user_id' => $user->id,
            'username' => 'alice',
            'display_name' => 'Alice Example',
            'bio' => 'Local photographer',
        ]);

        $response = $this->actingAs($user)->getJson('/account');

        $response
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'email' => 'alice@example.test',
                'username' => 'alice',
                'display_name' => 'Alice Example',
                'bio' => 'Local photographer',
            ])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    }

    public function test_a_guest_cannot_retrieve_account_data(): void
    {
        $this->getJson('/account')->assertUnauthorized();
    }
}
