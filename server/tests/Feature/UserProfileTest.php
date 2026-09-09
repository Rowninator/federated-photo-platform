<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_profile_have_a_one_to_one_relationship(): void
    {
        $user = User::factory()->create();
        $profile = Profile::create([
            'user_id' => $user->id,
            'username' => 'alice',
        ]);

        $this->assertTrue($user->profile->is($profile));
        $this->assertTrue($profile->user->is($user));
        $this->assertNull($profile->display_name);
        $this->assertNull($profile->bio);
    }

    public function test_a_user_cannot_have_more_than_one_profile(): void
    {
        $user = User::factory()->create();

        Profile::create([
            'user_id' => $user->id,
            'username' => 'alice',
        ]);

        $this->expectException(QueryException::class);

        Profile::create([
            'user_id' => $user->id,
            'username' => 'alice-second-profile',
        ]);
    }

    public function test_profile_user_must_exist(): void
    {
        $this->expectException(QueryException::class);

        Profile::create([
            'user_id' => 999,
            'username' => 'alice',
        ]);
    }

    public function test_profile_usernames_are_unique(): void
    {
        Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'alice',
        ]);

        $this->expectException(QueryException::class);

        Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'alice',
        ]);
    }

    public function test_user_emails_remain_unique(): void
    {
        User::factory()->create(['email' => 'alice@example.test']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'alice@example.test']);
    }
}
