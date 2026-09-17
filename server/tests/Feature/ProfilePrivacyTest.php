<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_enable_and_disable_follow_approval(): void
    {
        $profile = $this->createProfile('alice');
        $this->actingAs($profile->user);

        $this->patchJson('/profile/privacy', ['is_private' => true])
            ->assertOk()->assertExactJson(['is_private' => true]);
        $this->assertTrue($profile->fresh()->is_private);

        $this->patchJson('/profile/privacy', ['is_private' => false])
            ->assertOk()->assertExactJson(['is_private' => false]);
        $this->assertFalse($profile->fresh()->is_private);
    }

    public function test_guest_cannot_change_profile_privacy(): void
    {
        $profile = $this->createProfile('alice');

        $this->patchJson('/profile/privacy', ['is_private' => true])->assertUnauthorized();

        $this->assertFalse($profile->fresh()->is_private);
    }

    public function test_privacy_change_preserves_relationships_pending_requests_and_statuses(): void
    {
        $owner = $this->createProfile('alice', isPrivate: true);
        $follower = $this->createProfile('bob');
        $requester = $this->createProfile('carol');
        $follow = $follower->outgoingFollows()->create(['followed_profile_id' => $owner->id]);
        $request = $requester->outgoingFollowRequests()->create(['followed_profile_id' => $owner->id]);
        $status = $owner->statuses()->create(['caption' => 'Unchanged']);

        $this->actingAs($owner->user)->patchJson('/profile/privacy', ['is_private' => false])
            ->assertOk()->assertExactJson(['is_private' => false]);

        $this->assertModelExists($follow);
        $this->assertModelExists($request);
        $this->assertModelExists($status);
        $this->assertSame('Unchanged', $status->fresh()->caption);
        $this->assertDatabaseCount('follows', 1);
        $this->assertDatabaseCount('follow_requests', 1);
    }

    public function test_privacy_endpoint_updates_only_is_private(): void
    {
        $owner = $this->createProfile('alice');
        $other = $this->createProfile('bob');
        $ownerUserId = $owner->user_id;

        $this->actingAs($owner->user)->patchJson('/profile/privacy', [
            'is_private' => true,
            'user_id' => $other->user_id,
            'username' => 'changed',
            'display_name' => 'Changed',
            'bio' => 'Changed',
        ])->assertOk()->assertExactJson(['is_private' => true]);

        $owner->refresh();
        $this->assertTrue($owner->is_private);
        $this->assertSame('alice', $owner->username);
        $this->assertNull($owner->display_name);
        $this->assertNull($owner->bio);
        $this->assertSame($ownerUserId, $owner->user_id);
    }

    public function test_is_private_is_required_and_boolean(): void
    {
        $profile = $this->createProfile('alice');
        $this->actingAs($profile->user);

        foreach ([[], ['is_private' => null], ['is_private' => 'not-a-boolean']] as $input) {
            $this->patchJson('/profile/privacy', $input)
                ->assertUnprocessable()->assertJsonValidationErrors('is_private');
        }

        $this->assertFalse($profile->fresh()->is_private);
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
