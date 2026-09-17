<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveFollowerTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_can_remove_an_existing_follower_idempotently(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $follow = $alice->outgoingFollows()->create(['followed_profile_id' => $bob->id]);
        $url = '/followers/'.$alice->id;
        $this->actingAs($bob->user);

        $this->deleteJson($url)->assertOk()->assertExactJson(['follower' => false]);
        $this->assertModelMissing($follow);

        $this->deleteJson($url)->assertOk()->assertExactJson(['follower' => false]);
        $this->assertDatabaseCount('follows', 0);
        $this->assertDatabaseCount('follow_requests', 0);
    }

    public function test_reverse_unrelated_and_pending_relationships_remain_untouched(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $carol = $this->createProfile('carol');
        $targetedFollow = $alice->outgoingFollows()->create(['followed_profile_id' => $bob->id]);
        $reverseFollow = $bob->outgoingFollows()->create(['followed_profile_id' => $alice->id]);
        $unrelatedFollow = $carol->outgoingFollows()->create(['followed_profile_id' => $alice->id]);
        $incomingRequest = $alice->outgoingFollowRequests()->create(['followed_profile_id' => $bob->id]);
        $unrelatedRequest = $carol->outgoingFollowRequests()->create(['followed_profile_id' => $bob->id]);

        $this->actingAs($bob->user)->deleteJson('/followers/'.$alice->id)
            ->assertOk()->assertExactJson(['follower' => false]);

        $this->assertModelMissing($targetedFollow);
        foreach ([$reverseFollow, $unrelatedFollow, $incomingRequest, $unrelatedRequest] as $relationship) {
            $this->assertModelExists($relationship);
        }
        $this->assertDatabaseCount('follows', 2);
        $this->assertDatabaseCount('follow_requests', 2);
    }

    public function test_guest_cannot_remove_a_follower(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $follow = $alice->outgoingFollows()->create(['followed_profile_id' => $bob->id]);

        $this->deleteJson('/followers/'.$alice->id)->assertUnauthorized();

        $this->assertModelExists($follow);
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
        ]);
    }
}
