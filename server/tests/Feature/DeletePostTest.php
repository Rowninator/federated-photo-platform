<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DeletePostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    public function test_owner_deletes_post_media_dependents_and_interactions_without_affecting_unrelated_content(): void
    {
        $owner = $this->createProfile('alice');
        $commenter = $this->createProfile('bob');
        $reposter = $this->createProfile('carol');
        $interactor = $this->createProfile('dave');
        $post = $owner->statuses()->create(['caption' => 'Delete me']);
        $firstMedia = $this->createMedia($owner, $post, 'first');
        $secondMedia = $this->createMedia($owner, $post, 'second');
        $reply = $commenter->statuses()->create(['caption' => 'Reply', 'in_reply_to_id' => $post->id]);
        $repost = $reposter->statuses()->create(['reblog_of_id' => $post->id]);

        foreach ([$post, $reply, $repost] as $status) {
            $interactor->likes()->create(['status_id' => $status->id]);
            $interactor->bookmarks()->create(['status_id' => $status->id]);
        }

        $unrelated = $owner->statuses()->create(['caption' => 'Keep me']);
        $unrelatedMedia = $this->createMedia($owner, $unrelated, 'unrelated');
        $unrelatedLike = $interactor->likes()->create(['status_id' => $unrelated->id]);
        $unrelatedBookmark = $interactor->bookmarks()->create(['status_id' => $unrelated->id]);

        $this->actingAs($owner->user)->deleteJson('/posts/'.$post->id)->assertNoContent();

        foreach ([$post, $reply, $repost] as $deletedStatus) {
            $this->assertModelMissing($deletedStatus);
        }
        foreach ([$firstMedia, $secondMedia] as $deletedMedia) {
            $this->assertModelMissing($deletedMedia);
            Storage::disk('media')->assertMissing($this->paths($deletedMedia));
        }
        $this->assertDatabaseMissing('likes', ['status_id' => $post->id]);
        $this->assertDatabaseMissing('likes', ['status_id' => $reply->id]);
        $this->assertDatabaseMissing('likes', ['status_id' => $repost->id]);
        $this->assertDatabaseMissing('bookmarks', ['status_id' => $post->id]);
        $this->assertDatabaseMissing('bookmarks', ['status_id' => $reply->id]);
        $this->assertDatabaseMissing('bookmarks', ['status_id' => $repost->id]);

        $this->assertModelExists($unrelated);
        $this->assertModelExists($unrelatedMedia);
        $this->assertModelExists($unrelatedLike);
        $this->assertModelExists($unrelatedBookmark);
        Storage::disk('media')->assertExists($this->paths($unrelatedMedia));
    }

    public function test_guests_and_other_profiles_cannot_delete_a_post(): void
    {
        $owner = $this->createProfile('alice');
        $other = $this->createProfile('bob');
        $post = $owner->statuses()->create([]);
        $media = $this->createMedia($owner, $post, 'target');
        $url = '/posts/'.$post->id;

        $this->deleteJson($url)->assertUnauthorized();
        $this->actingAs($other->user)->deleteJson($url)->assertForbidden();

        $this->assertModelExists($post);
        $this->assertModelExists($media);
        Storage::disk('media')->assertExists($this->paths($media));
    }

    public function test_reply_and_repost_rows_cannot_be_deleted_through_the_post_endpoint(): void
    {
        $profile = $this->createProfile('alice');
        $post = $profile->statuses()->create([]);
        $reply = $profile->statuses()->create(['caption' => 'Reply', 'in_reply_to_id' => $post->id]);
        $repost = $profile->statuses()->create(['reblog_of_id' => $post->id]);
        $this->actingAs($profile->user);

        foreach ([$reply, $repost] as $status) {
            $this->deleteJson('/posts/'.$status->id)->assertNotFound();
            $this->assertModelExists($status);
        }

        $this->assertModelExists($post);
    }

    public function test_filesystem_failure_rolls_back_database_deletions_and_allows_retry(): void
    {
        $owner = $this->createProfile('alice');
        $commenter = $this->createProfile('bob');
        $post = $owner->statuses()->create([]);
        $firstMedia = $this->createMedia($owner, $post, 'first');
        $secondMedia = $this->createMedia($owner, $post, 'second');
        $reply = $commenter->statuses()->create(['caption' => 'Reply', 'in_reply_to_id' => $post->id]);
        $like = $owner->likes()->create(['status_id' => $reply->id]);
        $disk = Storage::disk('media');
        $failingDisk = Mockery::mock($disk);

        foreach ($this->paths($firstMedia) as $path) {
            $failingDisk->shouldReceive('delete')->with($path)->once()
                ->andReturnUsing(fn (string $path): bool => $disk->delete($path));
        }
        $failingDisk->shouldReceive('delete')->with($secondMedia->original_path)->once()
            ->andReturnUsing(fn (string $path): bool => $disk->delete($path));
        $failingDisk->shouldReceive('delete')->with($secondMedia->display_path)->once()->andReturn(false);
        Storage::set('media', $failingDisk);

        $this->actingAs($owner->user)->deleteJson('/posts/'.$post->id)->assertInternalServerError();

        foreach ([$post, $reply, $firstMedia, $secondMedia, $like] as $model) {
            $this->assertModelExists($model);
        }
        $disk->assertMissing($this->paths($firstMedia));
        $disk->assertMissing($secondMedia->original_path);
        $disk->assertExists([$secondMedia->display_path, $secondMedia->thumbnail_path]);

        Storage::set('media', $disk);
        $this->deleteJson('/posts/'.$post->id)->assertNoContent();

        foreach ([$post, $reply, $firstMedia, $secondMedia, $like] as $model) {
            $this->assertModelMissing($model);
        }
        $disk->assertMissing([...$this->paths($firstMedia), ...$this->paths($secondMedia)]);
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
        ]);
    }

    private function createMedia(Profile $profile, Status $status, string $name): Media
    {
        $media = $profile->media()->create([
            'status_id' => $status->id,
            'position' => 0,
            'disk' => 'media',
            'original_path' => 'originals/'.$name.'.jpg',
            'display_path' => 'display/'.$name.'.jpg',
            'thumbnail_path' => 'thumbnails/'.$name.'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 18,
            'width' => 800,
            'height' => 600,
        ]);

        foreach ($this->paths($media) as $path) {
            Storage::disk($media->disk)->put($path, 'stored image bytes');
        }

        return $media;
    }

    /**
     * @return array<string, string>
     */
    private function paths(Media $media): array
    {
        return $media->only(['original_path', 'display_path', 'thumbnail_path']);
    }
}
