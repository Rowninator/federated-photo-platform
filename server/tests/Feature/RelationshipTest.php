<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\FollowRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_profiles_are_public_by_default_and_privacy_is_boolean(): void
    {
        $profile = $this->createProfile('alice')->fresh();

        $this->assertFalse($profile->is_private);
        $this->assertDatabaseHas('profiles', ['id' => $profile->id, 'is_private' => false]);

        $profile->update(['is_private' => true]);

        $this->assertTrue($profile->fresh()->is_private);
    }

    public function test_follow_relationships_have_the_correct_direction(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $carol = $this->createProfile('carol');
        $aliceToBob = $alice->outgoingFollows()->create(['followed_profile_id' => $bob->id]);
        $carolToBob = $carol->outgoingFollows()->create(['followed_profile_id' => $bob->id]);

        $this->assertTrue($aliceToBob->followerProfile->is($alice));
        $this->assertTrue($aliceToBob->followedProfile->is($bob));
        $this->assertSame([$aliceToBob->id], $alice->outgoingFollows->modelKeys());
        $this->assertEqualsCanonicalizing([$aliceToBob->id, $carolToBob->id], $bob->incomingFollows->modelKeys());
        $this->assertSame([$bob->id], $alice->followingProfiles->modelKeys());
        $this->assertEqualsCanonicalizing([$alice->id, $carol->id], $bob->followerProfiles->modelKeys());
        $this->assertNotNull($aliceToBob->created_at);
        $this->assertNotNull($aliceToBob->updated_at);
    }

    public function test_follow_request_relationships_have_the_correct_direction(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $carol = $this->createProfile('carol');
        $aliceToBob = $alice->outgoingFollowRequests()->create(['followed_profile_id' => $bob->id]);
        $carolToBob = $carol->outgoingFollowRequests()->create(['followed_profile_id' => $bob->id]);

        $this->assertTrue($aliceToBob->followerProfile->is($alice));
        $this->assertTrue($aliceToBob->followedProfile->is($bob));
        $this->assertSame([$aliceToBob->id], $alice->outgoingFollowRequests->modelKeys());
        $this->assertEqualsCanonicalizing(
            [$aliceToBob->id, $carolToBob->id],
            $bob->incomingFollowRequests->modelKeys(),
        );
        $this->assertNotNull($aliceToBob->created_at);
        $this->assertNotNull($aliceToBob->updated_at);
    }

    /**
     * @param  class-string<Follow|FollowRequest>  $model
     */
    #[DataProvider('relationshipModels')]
    public function test_duplicate_relationship_pairs_are_rejected_by_the_database(string $model): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $attributes = [
            'follower_profile_id' => $alice->id,
            'followed_profile_id' => $bob->id,
        ];
        $model::create($attributes);

        $this->expectException(QueryException::class);

        $model::create($attributes);
    }

    public function test_deleting_a_profile_cascades_relationship_rows_in_both_directions(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $carol = $this->createProfile('carol');
        Follow::create(['follower_profile_id' => $alice->id, 'followed_profile_id' => $bob->id]);
        Follow::create(['follower_profile_id' => $bob->id, 'followed_profile_id' => $carol->id]);
        FollowRequest::create(['follower_profile_id' => $alice->id, 'followed_profile_id' => $bob->id]);
        FollowRequest::create(['follower_profile_id' => $bob->id, 'followed_profile_id' => $carol->id]);

        $bob->delete();

        $this->assertDatabaseCount('follows', 0);
        $this->assertDatabaseCount('follow_requests', 0);
        $this->assertModelExists($alice);
        $this->assertModelExists($carol);
    }

    /**
     * @return array<string, array{class-string<Follow|FollowRequest>}>
     */
    public static function relationshipModels(): array
    {
        return [
            'follow' => [Follow::class],
            'follow request' => [FollowRequest::class],
        ];
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
        ]);
    }
}
