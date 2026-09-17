<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomingFollowRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_profile_lists_only_its_incoming_pending_requests(): void
    {
        $owner = $this->createProfile('alice', 'Alice');
        $bob = $this->createProfile('bob', 'Bob');
        $carol = $this->createProfile('carol');
        $other = $this->createProfile('dave', 'Dave');
        $bobRequest = $bob->outgoingFollowRequests()->create(['followed_profile_id' => $owner->id]);
        $carolRequest = $carol->outgoingFollowRequests()->create(['followed_profile_id' => $owner->id]);

        // These rows are outgoing, addressed to another Profile, or already established.
        $owner->outgoingFollowRequests()->create(['followed_profile_id' => $bob->id]);
        $bob->outgoingFollowRequests()->create(['followed_profile_id' => $other->id]);
        $other->outgoingFollows()->create(['followed_profile_id' => $owner->id]);

        $this->actingAs($owner->user)->getJson('/follow-requests')
            ->assertOk()->assertExactJson([
                'requests' => [
                    [
                        'id' => $bobRequest->id,
                        'requester' => [
                            'id' => $bob->id,
                            'username' => 'bob',
                            'display_name' => 'Bob',
                        ],
                        'created_at' => $bobRequest->created_at->toISOString(),
                    ],
                    [
                        'id' => $carolRequest->id,
                        'requester' => [
                            'id' => $carol->id,
                            'username' => 'carol',
                            'display_name' => null,
                        ],
                        'created_at' => $carolRequest->created_at->toISOString(),
                    ],
                ],
            ]);
    }

    public function test_guest_cannot_list_follow_requests(): void
    {
        $target = $this->createProfile('alice');
        $this->createProfile('bob')->outgoingFollowRequests()->create([
            'followed_profile_id' => $target->id,
        ]);

        $this->getJson('/follow-requests')->assertUnauthorized();
    }

    private function createProfile(string $username, ?string $displayName = null): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
            'display_name' => $displayName,
            'bio' => 'Private response field',
        ]);
    }
}
