<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Services\FollowingProfileIdsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FollowingProfileIdsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_populates_only_established_ids_reuses_them_and_rebuilds_from_database(): void
    {
        $follower = $this->createProfile('alice');
        $first = $this->createProfile('bob');
        $second = $this->createProfile('carol');
        $pending = $this->createProfile('dave', isPrivate: true);
        $later = $this->createProfile('eve');
        $follower->outgoingFollows()->create(['followed_profile_id' => $first->id]);
        $follower->outgoingFollows()->create(['followed_profile_id' => $second->id]);
        $follower->outgoingFollowRequests()->create(['followed_profile_id' => $pending->id]);
        $cache = app(FollowingProfileIdsCache::class);

        $expected = [$first->id, $second->id];
        $this->assertSame($expected, $cache->get($follower));
        $this->assertSame($expected, Cache::get($cache->key($follower)));

        $follower->outgoingFollows()->create(['followed_profile_id' => $later->id]);
        $this->assertSame($expected, $cache->get($follower));

        $cache->forget($follower);

        $this->assertSame([$first->id, $second->id, $later->id], $cache->get($follower));
        $this->assertDatabaseCount('follows', 3);
        $this->assertDatabaseCount('follow_requests', 1);
    }

    public function test_public_follow_creation_invalidates_the_followers_cache(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $cache = app(FollowingProfileIdsCache::class);
        $this->assertSame([], $cache->get($follower));

        $this->actingAs($follower->user)
            ->postJson('/profiles/'.$target->id.'/follow')
            ->assertOk();

        $this->assertFalse(Cache::has($cache->key($follower)));
        $this->assertSame([$target->id], $cache->get($follower));
    }

    public function test_follow_request_acceptance_invalidates_the_requesters_cache(): void
    {
        $requester = $this->createProfile('alice');
        $target = $this->createProfile('bob', isPrivate: true);
        $followRequest = $requester->outgoingFollowRequests()->create([
            'followed_profile_id' => $target->id,
        ]);
        $cache = app(FollowingProfileIdsCache::class);
        $this->assertSame([], $cache->get($requester));

        $this->actingAs($target->user)
            ->postJson('/follow-requests/'.$followRequest->id.'/accept')
            ->assertOk();

        $this->assertFalse(Cache::has($cache->key($requester)));
        $this->assertSame([$target->id], $cache->get($requester));
    }

    public function test_unfollow_invalidates_the_followers_cache(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $follower->outgoingFollows()->create(['followed_profile_id' => $target->id]);
        $cache = app(FollowingProfileIdsCache::class);
        $this->assertSame([$target->id], $cache->get($follower));

        $this->actingAs($follower->user)
            ->deleteJson('/profiles/'.$target->id.'/follow')
            ->assertOk();

        $this->assertFalse(Cache::has($cache->key($follower)));
        $this->assertSame([], $cache->get($follower));
    }

    public function test_follower_removal_invalidates_only_the_removed_followers_cache(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob');
        $follower->outgoingFollows()->create(['followed_profile_id' => $target->id]);
        $cache = app(FollowingProfileIdsCache::class);
        $cache->get($follower);
        $cache->get($target);

        $this->actingAs($target->user)
            ->deleteJson('/followers/'.$follower->id)
            ->assertOk();

        $this->assertFalse(Cache::has($cache->key($follower)));
        $this->assertTrue(Cache::has($cache->key($target)));
        $this->assertSame([], $cache->get($follower));
    }

    public function test_pending_request_creation_cancellation_and_rejection_do_not_invalidate_cache(): void
    {
        $follower = $this->createProfile('alice');
        $target = $this->createProfile('bob', isPrivate: true);
        $cache = app(FollowingProfileIdsCache::class);
        $this->assertSame([], $cache->get($follower));
        $key = $cache->key($follower);

        $this->actingAs($follower->user)
            ->postJson('/profiles/'.$target->id.'/follow')
            ->assertOk();
        $this->assertTrue(Cache::has($key));

        $this->deleteJson('/profiles/'.$target->id.'/follow')->assertOk();
        $this->assertTrue(Cache::has($key));

        $followRequest = $follower->outgoingFollowRequests()->create([
            'followed_profile_id' => $target->id,
        ]);
        $this->actingAs($target->user)
            ->deleteJson('/follow-requests/'.$followRequest->id)
            ->assertOk();

        $this->assertTrue(Cache::has($key));
        $this->assertSame([], Cache::get($key));
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
