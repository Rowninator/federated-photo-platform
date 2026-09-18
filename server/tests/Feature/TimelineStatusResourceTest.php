<?php

namespace Tests\Feature;

use App\Http\Resources\TimelineStatusResource;
use App\Models\Media;
use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use App\Queries\PublicTimelineQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineStatusResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_status_representation_contains_only_public_fields_ordered_media_and_counts(): void
    {
        $author = $this->createProfile('alice', 'Alice');
        $other = $this->createProfile('bob', 'Bob');
        $post = $author->statuses()->create(['caption' => 'Local photos'])->fresh();
        $second = $this->createMedia($post, 1);
        $first = $this->createMedia($post, 0);
        $author->likes()->create(['status_id' => $post->id]);
        $other->likes()->create(['status_id' => $post->id]);
        $author->bookmarks()->create(['status_id' => $post->id]);
        $other->statuses()->create(['caption' => 'First reply', 'in_reply_to_id' => $post->id]);
        $other->statuses()->create(['caption' => 'Second reply', 'in_reply_to_id' => $post->id]);
        $other->statuses()->create(['reblog_of_id' => $post->id]);

        $timelineStatus = app(PublicTimelineQuery::class)->query()->findOrFail($post->id);

        $this->assertTrue($timelineStatus->relationLoaded('profile'));
        $this->assertTrue($timelineStatus->relationLoaded('media'));
        foreach (['likes_count', 'replies_count', 'reposts_count'] as $count) {
            $this->assertArrayHasKey($count, $timelineStatus->getAttributes());
        }

        $representation = (new TimelineStatusResource($timelineStatus))->resolve(request());

        $this->assertSame([
            'id' => $post->id,
            'caption' => 'Local photos',
            'created_at' => $post->created_at->toISOString(),
            'profile' => [
                'id' => $author->id,
                'username' => 'alice',
                'display_name' => 'Alice',
            ],
            'media' => [
                [
                    'id' => $first->id,
                    'mime_type' => 'image/jpeg',
                    'width' => 800,
                    'height' => 600,
                    'position' => 0,
                ],
                [
                    'id' => $second->id,
                    'mime_type' => 'image/jpeg',
                    'width' => 800,
                    'height' => 600,
                    'position' => 1,
                ],
            ],
            'counts' => [
                'likes' => 2,
                'replies' => 2,
                'reposts' => 1,
            ],
        ], $representation);

        $json = json_encode($representation, JSON_THROW_ON_ERROR);
        foreach ([
            'email', 'password', 'bookmark', 'disk',
            'original_path', 'display_path', 'thumbnail_path',
        ] as $privateField) {
            $this->assertStringNotContainsString($privateField, $json);
        }
    }

    private function createProfile(string $username, string $displayName): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
            'display_name' => $displayName,
        ]);
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
