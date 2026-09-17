<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\FollowRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnfollowProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_established_outgoing_follow_is_removed_idempotently(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $follow = $follower->outgoingFollows()->create(['followed_profile_id' => $target->id]);
        $url = '/profiles/'.$target->id.'/follow';
        $this->actingAs($follower->user);

        $this->deleteJson($url)->assertOk()->assertExactJson([
            'following' => false,
            'requested' => false,
        ]);
        $this->assertModelMissing($follow);

        $this->deleteJson($url)->assertOk()->assertExactJson([
            'following' => false,
            'requested' => false,
        ]);
        $this->assertDatabaseCount('follows', 0);
        $this->assertDatabaseCount('follow_requests', 0);
    }

    public function test_pending_outgoing_request_is_removed_idempotently(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob', isPrivate: true);
        $request = $follower->outgoingFollowRequests()->create(['followed_profile_id' => $target->id]);
        $url = '/profiles/'.$target->id.'/follow';
        $this->actingAs($follower->user);

        $this->deleteJson($url)->assertOk()->assertExactJson([
            'following' => false,
            'requested' => false,
        ]);
        $this->assertModelMissing($request);

        $this->deleteJson($url)->assertOk()->assertExactJson([
            'following' => false,
            'requested' => false,
        ]);
        $this->assertDatabaseCount('follows', 0);
        $this->assertDatabaseCount('follow_requests', 0);
    }

    public function test_reverse_and_unrelated_relationships_remain_untouched(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $other = $this->createProfile('carol');
        $targetedFollow = $follower->outgoingFollows()->create(['followed_profile_id' => $target->id]);
        $targetedRequest = $follower->outgoingFollowRequests()->create(['followed_profile_id' => $target->id]);
        $reverseFollow = $target->outgoingFollows()->create(['followed_profile_id' => $follower->id]);
        $incomingRequest = $target->outgoingFollowRequests()->create(['followed_profile_id' => $follower->id]);
        $unrelatedFollow = $follower->outgoingFollows()->create(['followed_profile_id' => $other->id]);
        $unrelatedRequest = $other->outgoingFollowRequests()->create(['followed_profile_id' => $target->id]);

        $this->actingAs($follower->user)->deleteJson('/profiles/'.$target->id.'/follow')
            ->assertOk()->assertExactJson([
                'following' => false,
                'requested' => false,
            ]);

        $this->assertModelMissing($targetedFollow);
        $this->assertModelMissing($targetedRequest);
        foreach ([$reverseFollow, $incomingRequest, $unrelatedFollow, $unrelatedRequest] as $relationship) {
            $this->assertModelExists($relationship);
        }
        $this->assertDatabaseCount('follows', 2);
        $this->assertDatabaseCount('follow_requests', 2);
    }

    public function test_guest_cannot_unfollow_or_cancel_a_request(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $follow = Follow::create([
            'follower_profile_id' => $follower->id,
            'followed_profile_id' => $target->id,
        ]);
        $request = FollowRequest::create([
            'follower_profile_id' => $follower->id,
            'followed_profile_id' => $target->id,
        ]);

        $this->deleteJson('/profiles/'.$target->id.'/follow')->assertUnauthorized();

        $this->assertModelExists($follow);
        $this->assertModelExists($request);
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
