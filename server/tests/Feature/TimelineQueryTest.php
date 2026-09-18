<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use App\Queries\HomeTimelineQuery;
use App\Queries\PublicTimelineQuery;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_timeline_contains_only_self_and_established_followed_top_level_posts(): void
    {
        $viewer = $this->createProfile('alice');
        $followed = $this->createProfile('bob');
        $pending = $this->createProfile('carol', isPrivate: true);
        $unrelated = $this->createProfile('dave');
        $viewer->outgoingFollows()->create(['followed_profile_id' => $followed->id]);
        $viewer->outgoingFollowRequests()->create(['followed_profile_id' => $pending->id]);

        $selfPost = $viewer->statuses()->create(['caption' => 'Self']);
        $followedPost = $followed->statuses()->create(['caption' => 'Followed']);
        $pendingPost = $pending->statuses()->create(['caption' => 'Pending']);
        $unrelatedPost = $unrelated->statuses()->create(['caption' => 'Unrelated']);
        $reply = $followed->statuses()->create([
            'caption' => 'Reply',
            'in_reply_to_id' => $selfPost->id,
        ]);
        $repost = $followed->statuses()->create(['reblog_of_id' => $selfPost->id]);

        $statuses = app(HomeTimelineQuery::class)->for($viewer)->get();

        $this->assertEqualsCanonicalizing([$selfPost->id, $followedPost->id], $statuses->modelKeys());
        foreach ([$pendingPost, $unrelatedPost, $reply, $repost] as $excluded) {
            $this->assertNotContains($excluded->id, $statuses->modelKeys());
        }
    }

    public function test_home_timeline_orders_by_created_at_then_id_descending(): void
    {
        $viewer = $this->createProfile('alice');
        $followed = $this->createProfile('bob');
        $viewer->outgoingFollows()->create(['followed_profile_id' => $followed->id]);
        $time = CarbonImmutable::parse('2026-01-01 12:00:00');
        $oldest = $this->createStatusAt($viewer, $time->subMinute());
        $tiedFirst = $this->createStatusAt($viewer, $time);
        $tiedSecond = $this->createStatusAt($followed, $time);
        $newest = $this->createStatusAt($followed, $time->addMinute());

        $statuses = app(HomeTimelineQuery::class)->for($viewer)->get();

        $this->assertSame(
            [$newest->id, $tiedSecond->id, $tiedFirst->id, $oldest->id],
            $statuses->modelKeys(),
        );
    }

    public function test_public_timeline_contains_all_local_top_level_posts_regardless_of_profile_privacy(): void
    {
        $publicProfile = $this->createProfile('alice');
        $privateProfile = $this->createProfile('bob', isPrivate: true);
        $publicPost = $publicProfile->statuses()->create(['caption' => 'Public profile']);
        $privatePost = $privateProfile->statuses()->create(['caption' => 'Private profile']);
        $reply = $privateProfile->statuses()->create([
            'caption' => 'Reply',
            'in_reply_to_id' => $publicPost->id,
        ]);
        $repost = $privateProfile->statuses()->create(['reblog_of_id' => $publicPost->id]);

        $statuses = app(PublicTimelineQuery::class)->query()->get();

        $this->assertEqualsCanonicalizing([$publicPost->id, $privatePost->id], $statuses->modelKeys());
        $this->assertNotContains($reply->id, $statuses->modelKeys());
        $this->assertNotContains($repost->id, $statuses->modelKeys());
    }

    public function test_public_timeline_orders_by_created_at_then_id_descending(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $time = CarbonImmutable::parse('2026-01-01 12:00:00');
        $oldest = $this->createStatusAt($alice, $time->subMinute());
        $tiedFirst = $this->createStatusAt($alice, $time);
        $tiedSecond = $this->createStatusAt($bob, $time);
        $newest = $this->createStatusAt($bob, $time->addMinute());

        $statuses = app(PublicTimelineQuery::class)->query()->get();

        $this->assertSame(
            [$newest->id, $tiedSecond->id, $tiedFirst->id, $oldest->id],
            $statuses->modelKeys(),
        );
    }

    private function createProfile(string $username, bool $isPrivate = false): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
            'is_private' => $isPrivate,
        ]);
    }

    private function createStatusAt(Profile $profile, DateTimeInterface $createdAt): Status
    {
        $status = $profile->statuses()->create([]);

        Status::withoutTimestamps(function () use ($status, $createdAt): void {
            $status->forceFill(['created_at' => $createdAt])->save();
        });

        return $status->refresh();
    }
}
