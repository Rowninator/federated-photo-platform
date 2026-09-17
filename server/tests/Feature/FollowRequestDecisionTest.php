<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowRequestDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_profile_can_accept_a_request_atomically(): void
    {
        $requester = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $followRequest = $requester->outgoingFollowRequests()->create([
            'followed_profile_id' => $target->id,
        ]);

        $this->actingAs($target->user)->postJson('/follow-requests/'.$followRequest->id.'/accept')
            ->assertOk()->assertExactJson([
                'following' => true,
                'requested' => false,
            ]);

        $this->assertModelMissing($followRequest);
        $this->assertDatabaseCount('follow_requests', 0);
        $this->assertDatabaseHas('follows', [
            'follower_profile_id' => $requester->id,
            'followed_profile_id' => $target->id,
        ]);
        $this->assertDatabaseCount('follows', 1);
    }

    public function test_target_profile_can_reject_a_request_without_creating_a_follow(): void
    {
        $requester = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $followRequest = $requester->outgoingFollowRequests()->create([
            'followed_profile_id' => $target->id,
        ]);

        $this->actingAs($target->user)->deleteJson('/follow-requests/'.$followRequest->id)
            ->assertOk()->assertExactJson([
                'following' => false,
                'requested' => false,
            ]);

        $this->assertModelMissing($followRequest);
        $this->assertDatabaseCount('follow_requests', 0);
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_requester_and_unrelated_profile_cannot_decide_a_request(): void
    {
        $requester = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $unrelated = $this->createProfile('carol');
        $followRequest = $requester->outgoingFollowRequests()->create([
            'followed_profile_id' => $target->id,
        ]);
        $acceptUrl = '/follow-requests/'.$followRequest->id.'/accept';
        $rejectUrl = '/follow-requests/'.$followRequest->id;

        foreach ([$requester, $unrelated] as $profile) {
            $this->actingAs($profile->user)->postJson($acceptUrl)->assertForbidden();
            $this->deleteJson($rejectUrl)->assertForbidden();
            $this->assertModelExists($followRequest);
        }

        $this->assertDatabaseCount('follow_requests', 1);
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_acceptance_preserves_an_existing_follow_and_cannot_duplicate_it(): void
    {
        $requester = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $follow = $requester->outgoingFollows()->create(['followed_profile_id' => $target->id]);
        $followRequest = $requester->outgoingFollowRequests()->create([
            'followed_profile_id' => $target->id,
        ]);
        $url = '/follow-requests/'.$followRequest->id.'/accept';
        $this->actingAs($target->user);

        $this->postJson($url)->assertOk();

        $this->assertModelExists($follow);
        $this->assertModelMissing($followRequest);
        $this->assertDatabaseCount('follows', 1);
        $this->assertSame($follow->id, $requester->outgoingFollows()->sole()->id);

        $this->postJson($url)->assertNotFound();
        $this->assertDatabaseCount('follows', 1);
    }

    public function test_guests_cannot_accept_or_reject_requests(): void
    {
        $requester = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $followRequest = $requester->outgoingFollowRequests()->create([
            'followed_profile_id' => $target->id,
        ]);

        $this->postJson('/follow-requests/'.$followRequest->id.'/accept')->assertUnauthorized();
        $this->deleteJson('/follow-requests/'.$followRequest->id)->assertUnauthorized();

        $this->assertModelExists($followRequest);
        $this->assertDatabaseCount('follows', 0);
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
        ]);
    }
}
