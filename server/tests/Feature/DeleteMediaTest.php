<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DeleteMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    public function test_guests_cannot_delete_media(): void
    {
        $media = $this->createMedia(User::factory()->create(), 'target');

        $this->deleteJson('/media/'.$media->id)->assertUnauthorized();

        $this->assertModelExists($media);
        Storage::disk('media')->assertExists($this->paths($media));
    }

    public function test_another_profiles_user_cannot_delete_media(): void
    {
        $media = $this->createMedia(User::factory()->create(), 'target');
        $otherUser = User::factory()->create();
        $otherUser->profile()->create(['username' => 'other']);

        $this->actingAs($otherUser)->deleteJson('/media/'.$media->id)->assertForbidden();

        $this->assertModelExists($media);
        Storage::disk('media')->assertExists($this->paths($media));
    }

    public function test_owner_deletes_only_the_target_media_and_its_three_files(): void
    {
        $owner = User::factory()->create();
        $target = $this->createMedia($owner, 'target');
        $sameOwnerMedia = $this->createMedia($owner, 'same-owner');
        $otherOwnerMedia = $this->createMedia(User::factory()->create(), 'other-owner');
        $disk = Storage::disk('media');

        $this->actingAs($owner)->deleteJson('/media/'.$target->id)->assertNoContent();

        $this->assertModelMissing($target);
        $disk->assertMissing($this->paths($target));
        $this->assertDatabaseCount('media', 2);

        foreach ([$sameOwnerMedia, $otherOwnerMedia] as $remaining) {
            $this->assertModelExists($remaining);
            foreach ($this->paths($remaining) as $path) {
                $this->assertSame('stored image bytes', $disk->get($path));
            }
        }
    }

    public function test_file_deletion_failure_keeps_the_row_and_allows_a_retry(): void
    {
        $owner = User::factory()->create();
        $media = $this->createMedia($owner, 'target');
        $disk = Storage::disk('media');
        $failingDisk = Mockery::mock($disk);
        $failingDisk->shouldReceive('delete')->with($media->original_path)->once()
            ->andReturnUsing(fn (string $path): bool => $disk->delete($path));
        $failingDisk->shouldReceive('delete')->with($media->display_path)->once()->andReturn(false);
        Storage::set('media', $failingDisk);

        $this->actingAs($owner)->deleteJson('/media/'.$media->id)->assertInternalServerError();

        $this->assertModelExists($media);
        $disk->assertMissing($media->original_path);
        $disk->assertExists([$media->display_path, $media->thumbnail_path]);

        Storage::set('media', $disk);
        $this->deleteJson('/media/'.$media->id)->assertNoContent();

        $this->assertModelMissing($media);
        $disk->assertMissing($this->paths($media));
    }

    private function createMedia(User $user, string $name): Media
    {
        $profile = $user->profile()->firstOrCreate([], ['username' => 'user'.$user->id]);
        $media = $profile->media()->create([
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
