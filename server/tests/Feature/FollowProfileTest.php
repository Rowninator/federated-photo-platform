<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\FollowRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_follow_is_established_and_idempotent(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $url = '/profiles/'.$target->id.'/follow';
        $this->actingAs($follower->user);

        $this->postJson($url)->assertOk()->assertExactJson([
            'following' => true,
            'requested' => false,
        ]);
        $follow = Follow::sole();
        $this->postJson($url)->assertOk()->assertExactJson([
            'following' => true,
            'requested' => false,
        ]);

        $this->assertDatabaseCount('follows', 1);
        $this->assertDatabaseCount('follow_requests', 0);
        $this->assertSame($follow->id, Follow::sole()->id);
        $this->assertTrue($follow->followerProfile->is($follower));
        $this->assertTrue($follow->followedProfile->is($target));
    }

    public function test_private_follow_request_is_created_and_idempotent(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob', isPrivate: true);
        $url = '/profiles/'.$target->id.'/follow';
        $this->actingAs($follower->user);

        $this->postJson($url)->assertOk()->assertExactJson([
            'following' => false,
            'requested' => true,
        ]);
        $request = FollowRequest::sole();
        $this->postJson($url)->assertOk()->assertExactJson([
            'following' => false,
            'requested' => true,
        ]);

        $this->assertDatabaseCount('follows', 0);
        $this->assertDatabaseCount('follow_requests', 1);
        $this->assertSame($request->id, FollowRequest::sole()->id);
        $this->assertTrue($request->followerProfile->is($follower));
        $this->assertTrue($request->followedProfile->is($target));
    }

    public function test_self_follow_is_rejected(): void
    {
        $profile = $this->createProfile('alice');

        $this->actingAs($profile->user)->postJson('/profiles/'.$profile->id.'/follow')
            ->assertUnprocessable()->assertJsonValidationErrors('profile');

        $this->assertDatabaseCount('follows', 0);
        $this->assertDatabaseCount('follow_requests', 0);
    }

    public function test_existing_follow_remains_when_target_becomes_private_without_leaving_a_request(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $follow = $follower->outgoingFollows()->create(['followed_profile_id' => $target->id]);
        $staleRequest = $follower->outgoingFollowRequests()->create(['followed_profile_id' => $target->id]);
        $target->update(['is_private' => true]);

        $this->actingAs($follower->user)->postJson('/profiles/'.$target->id.'/follow')
            ->assertOk()->assertExactJson([
                'following' => true,
                'requested' => false,
            ]);

        $this->assertModelExists($follow);
        $this->assertModelMissing($staleRequest);
        $this->assertDatabaseCount('follows', 1);
        $this->assertDatabaseCount('follow_requests', 0);
    }

    public function test_stale_request_is_replaced_when_target_is_now_public(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob', isPrivate: true);
        $request = $follower->outgoingFollowRequests()->create(['followed_profile_id' => $target->id]);
        $target->update(['is_private' => false]);

        $this->actingAs($follower->user)->postJson('/profiles/'.$target->id.'/follow')
            ->assertOk()->assertExactJson([
                'following' => true,
                'requested' => false,
            ]);

        $this->assertModelMissing($request);
        $this->assertDatabaseHas('follows', [
            'follower_profile_id' => $follower->id,
            'followed_profile_id' => $target->id,
        ]);
        $this->assertDatabaseCount('follows', 1);
        $this->assertDatabaseCount('follow_requests', 0);
    }

    public function test_guest_cannot_follow_a_profile(): void
    {
        $target = $this->createProfile('bob');

        $this->postJson('/profiles/'.$target->id.'/follow')->assertUnauthorized();

        $this->assertDatabaseCount('follows', 0);
        $this->assertDatabaseCount('follow_requests', 0);
    }

    private function createProfile(string $username, bool $isPrivate = false): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
            'is_private' => $isPrivate,
        ]);
    }
}
