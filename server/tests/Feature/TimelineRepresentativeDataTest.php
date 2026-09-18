<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineRepresentativeDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_local_graph_produces_expected_home_and_public_timelines(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $carol = $this->createProfile('carol');
        $dave = $this->createProfile('dave');
        $pending = $this->createProfile('eve', isPrivate: true);
        $alice->outgoingFollows()->create(['followed_profile_id' => $bob->id]);
        $alice->outgoingFollows()->create(['followed_profile_id' => $carol->id]);
        $alice->outgoingFollowRequests()->create(['followed_profile_id' => $pending->id]);

        $time = CarbonImmutable::parse('2026-01-01 12:00:00');
        $aliceOld = $this->createStatusAt($alice, $time->addMinutes(4));
        $bobNew = $this->createStatusAt($bob, $time->addMinutes(8));
        $carolTieFirst = $this->createStatusAt($carol, $time->addMinutes(6));
        $daveNew = $this->createStatusAt($dave, $time->addMinutes(9));
        $pendingPost = $this->createStatusAt($pending, $time->addMinutes(7));
        $aliceNew = $this->createStatusAt($alice, $time->addMinutes(8));
        $bobOld = $this->createStatusAt($bob, $time->addMinutes(2));
        $carolTieSecond = $this->createStatusAt($carol, $time->addMinutes(6));
        $daveOld = $this->createStatusAt($dave, $time->addMinutes(3));
        $reply = $this->createStatusAt($dave, $time->addMinutes(11), [
            'caption' => 'Reply',
            'in_reply_to_id' => $aliceOld->id,
        ]);
        $repost = $this->createStatusAt($carol, $time->addMinutes(10), [
            'reblog_of_id' => $bobNew->id,
        ]);

        $home = $this->actingAs($alice->user)->getJson('/timelines/home?limit=40')->assertOk();
        $homeIds = collect($home->json('data'))->pluck('id')->all();
        $homeAuthors = collect($home->json('data'))->pluck('profile.username')->all();

        $this->assertSame([
            $aliceNew->id,
            $bobNew->id,
            $carolTieSecond->id,
            $carolTieFirst->id,
            $aliceOld->id,
            $bobOld->id,
        ], $homeIds);
        $this->assertSame(['alice', 'bob', 'carol', 'carol', 'alice', 'bob'], $homeAuthors);
        foreach ([$daveNew, $daveOld, $pendingPost, $reply, $repost] as $excluded) {
            $this->assertNotContains($excluded->id, $homeIds);
        }

        $public = $this->getJson('/timelines/public?limit=40')->assertOk();
        $publicIds = collect($public->json('data'))->pluck('id')->all();
        $publicAuthors = collect($public->json('data'))->pluck('profile.username')->all();

        $this->assertSame([
            $daveNew->id,
            $aliceNew->id,
            $bobNew->id,
            $pendingPost->id,
            $carolTieSecond->id,
            $carolTieFirst->id,
            $aliceOld->id,
            $daveOld->id,
            $bobOld->id,
        ], $publicIds);
        $this->assertSame(
            ['dave', 'alice', 'bob', 'eve', 'carol', 'carol', 'alice', 'dave', 'bob'],
            $publicAuthors,
        );
        $this->assertNotContains($reply->id, $publicIds);
        $this->assertNotContains($repost->id, $publicIds);
    }

    private function createProfile(string $username, bool $isPrivate = false): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
            'is_private' => $isPrivate,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createStatusAt(
        Profile $profile,
        DateTimeInterface $createdAt,
        array $attributes = [],
    ): Status {
        $status = $profile->statuses()->create($attributes);

        Status::withoutTimestamps(function () use ($status, $createdAt): void {
            $status->forceFill(['created_at' => $createdAt])->save();
        });

        return $status->refresh();
    }
}
