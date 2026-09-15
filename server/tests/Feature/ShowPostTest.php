<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_level_post_returns_only_public_fields_with_ordered_media_and_counts(): void
    {
        $profile = $this->createProfile();
        $post = $profile->statuses()->create(['caption' => 'Local photos'])->fresh();
        $last = $this->createMedia($post, 1);
        $first = $this->createMedia($post, 0);
        $profile->statuses()->create(['in_reply_to_id' => $post->id, 'caption' => 'First reply']);
        $profile->statuses()->create(['in_reply_to_id' => $post->id, 'caption' => 'Second reply']);
        $profile->statuses()->create(['reblog_of_id' => $post->id]);
        $other = $profile->statuses()->create([]);
        $this->createMedia($other, 0);
        $profile->statuses()->create(['in_reply_to_id' => $other->id]);
        $profile->statuses()->create(['reblog_of_id' => $other->id]);

        // Exact JSON checks also prevent storage paths and account fields from leaking.
        $this->getJson('/posts/'.$post->id)->assertOk()->assertExactJson([
            'id' => $post->id,
            'caption' => 'Local photos',
            'created_at' => $post->created_at->toISOString(),
            'profile' => ['username' => 'alice', 'display_name' => 'Alice'],
            'media' => [
                ['id' => $first->id, 'mime_type' => 'image/jpeg', 'width' => 800, 'height' => 600],
                ['id' => $last->id, 'mime_type' => 'image/jpeg', 'width' => 800, 'height' => 600],
            ],
            'counts' => ['likes' => 0, 'replies' => 2, 'reposts' => 1],
        ]);
    }

    public function test_replies_reposts_and_missing_statuses_are_not_exposed(): void
    {
        $profile = $this->createProfile();
        $original = $profile->statuses()->create([]);
        $reply = $profile->statuses()->create(['in_reply_to_id' => $original->id]);
        $repost = $profile->statuses()->create(['reblog_of_id' => $original->id]);

        foreach ([$reply->id, $repost->id, 999] as $id) {
            $this->getJson('/posts/'.$id)->assertNotFound();
        }
    }

    private function createProfile(): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'alice',
            'display_name' => 'Alice',
            'bio' => 'Not included in the post response.',
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
