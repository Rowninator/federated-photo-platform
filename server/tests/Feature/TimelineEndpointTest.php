<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_timeline_requires_authentication(): void
    {
        $this->getJson('/timelines/home')->assertUnauthorized();
    }

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

        $response = $this->actingAs($viewer->user)->getJson('/timelines/home?limit=40')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$selfPost->id, $followedPost->id], $ids);
        foreach ([$pendingPost, $unrelatedPost, $reply, $repost] as $excluded) {
            $this->assertNotContains($excluded->id, $ids);
        }
    }

    public function test_public_timeline_is_guest_readable_and_includes_private_profile_posts(): void
    {
        $publicProfile = $this->createProfile('alice');
        $privateProfile = $this->createProfile('bob', isPrivate: true);
        $publicPost = $publicProfile->statuses()->create(['caption' => 'Public']);
        $privatePost = $privateProfile->statuses()->create(['caption' => 'Approval only']);

        $response = $this->getJson('/timelines/public')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$publicPost->id, $privatePost->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_public_timeline_preserves_status_and_media_order_without_private_fields(): void
    {
        $author = $this->createProfile('alice');
        $time = CarbonImmutable::parse('2026-01-01 12:00:00');
        $oldest = $this->createStatusAt($author, $time->subMinute());
        $tiedFirst = $this->createStatusAt($author, $time);
        $tiedSecond = $this->createStatusAt($author, $time);
        $newest = $this->createStatusAt($author, $time->addMinute());
        $lastMedia = $this->createMedia($newest, 1);
        $firstMedia = $this->createMedia($newest, 0);
        $author->bookmarks()->create(['status_id' => $newest->id]);

        $response = $this->getJson('/timelines/public?limit=40')->assertOk();

        $this->assertSame(
            [$newest->id, $tiedSecond->id, $tiedFirst->id, $oldest->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
        $this->assertSame(
            [$firstMedia->id, $lastMedia->id],
            collect($response->json('data.0.media'))->pluck('id')->all(),
        );
        $this->assertSame(
            ['id', 'username', 'display_name'],
            array_keys($response->json('data.0.profile')),
        );

        $json = $response->getContent();
        foreach ([
            'email', 'password', 'bookmark', 'disk',
            'original_path', 'display_path', 'thumbnail_path',
        ] as $privateField) {
            $this->assertStringNotContainsString($privateField, $json);
        }
    }

    public function test_default_limit_is_twenty(): void
    {
        $profile = $this->createProfile('alice');
        foreach (range(1, 21) as $number) {
            $profile->statuses()->create(['caption' => 'Post '.$number]);
        }

        $response = $this->getJson('/timelines/public')->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertSame(20, $response->json('meta.per_page'));
        $this->assertNotNull($response->json('meta.next_cursor'));
    }

    public function test_custom_limit_is_honored_and_next_cursor_is_usable(): void
    {
        $profile = $this->createProfile('alice');
        foreach (range(1, 3) as $number) {
            $profile->statuses()->create(['caption' => 'Post '.$number]);
        }

        $firstPage = $this->getJson('/timelines/public?limit=2')->assertOk();
        $nextUrl = $firstPage->json('links.next');

        $this->assertCount(2, $firstPage->json('data'));
        $this->assertSame(2, $firstPage->json('meta.per_page'));
        $this->assertNotNull($firstPage->json('meta.next_cursor'));
        $this->assertIsString($nextUrl);
        $this->assertStringContainsString('limit=2', $nextUrl);

        $secondPage = $this->getJson($nextUrl)->assertOk();

        $this->assertCount(1, $secondPage->json('data'));
        $this->assertEmpty(array_intersect(
            collect($firstPage->json('data'))->pluck('id')->all(),
            collect($secondPage->json('data'))->pluck('id')->all(),
        ));
    }

    public function test_home_timeline_cursor_remains_stable_when_newer_posts_are_inserted(): void
    {
        $profile = $this->createProfile('alice');
        $time = CarbonImmutable::parse('2026-01-01 12:00:00');
        $first = $this->createStatusAt($profile, $time->addMinutes(5));
        $second = $this->createStatusAt($profile, $time->addMinutes(4));
        $third = $this->createStatusAt($profile, $time->addMinutes(3));
        $fourth = $this->createStatusAt($profile, $time->addMinutes(2));
        $this->createStatusAt($profile, $time->addMinute());

        $firstPage = $this->actingAs($profile->user)
            ->getJson('/timelines/home?limit=2')
            ->assertOk();
        $firstPageIds = collect($firstPage->json('data'))->pluck('id')->all();
        $nextUrl = $firstPage->json('links.next');

        $this->assertSame([$first->id, $second->id], $firstPageIds);
        $this->assertIsString($nextUrl);

        $newest = $this->createStatusAt($profile, $time->addMinutes(7));
        $nextNewest = $this->createStatusAt($profile, $time->addMinutes(6));
        $insertedIds = [$newest->id, $nextNewest->id];

        $secondPage = $this->getJson($nextUrl)->assertOk();
        $secondPageIds = collect($secondPage->json('data'))->pluck('id')->all();

        $this->assertSame([$third->id, $fourth->id], $secondPageIds);
        $this->assertEmpty(array_intersect($firstPageIds, $secondPageIds));
        $this->assertEmpty(array_intersect($insertedIds, $secondPageIds));
    }

    public function test_invalid_limits_are_rejected(): void
    {
        foreach ([0, 41, 'not-an-integer'] as $limit) {
            $this->getJson('/timelines/public?limit='.$limit)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('limit');
        }
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

    private function createMedia(Status $status, int $position): Media
    {
        return $status->media()->create([
            'profile_id' => $status->profile_id,
            'position' => $position,
            'disk' => 'media',
            'original_path' => $status->id.'/'.$position.'/original.jpg',
            'display_path' => $status->id.'/'.$position.'/display.jpg',
            'thumbnail_path' => $status->id.'/'.$position.'/thumbnail.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12345,
            'width' => 800,
            'height' => 600,
        ]);
    }
}
