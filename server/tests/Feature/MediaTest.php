<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_metadata_and_ownership_are_persisted(): void
    {
        $profile = $this->createProfile('alice');
        $attributes = $this->mediaAttributes();

        $media = $profile->media()->create($attributes)->fresh();

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'profile_id' => $profile->id,
            ...$attributes,
        ]);
        $this->assertTrue($media->profile->is($profile));
        $this->assertSame($attributes['size_bytes'], $media->size_bytes);
        $this->assertSame($attributes['width'], $media->width);
        $this->assertSame($attributes['height'], $media->height);
        $this->assertNotNull($media->created_at);
        $this->assertNotNull($media->updated_at);
    }

    public function test_a_profile_has_many_media_without_including_another_profiles_media(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $first = $alice->media()->create($this->mediaAttributes());
        $second = $alice->media()->create($this->mediaAttributes('second'));
        $bob->media()->create($this->mediaAttributes('bob'));

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $alice->media->modelKeys());
    }

    public function test_media_requires_an_existing_profile(): void
    {
        $this->expectException(QueryException::class);

        Media::create([
            'profile_id' => 999,
            ...$this->mediaAttributes(),
        ]);
    }

    public function test_a_profile_with_media_cannot_be_deleted(): void
    {
        $profile = $this->createProfile('alice');
        $profile->media()->create($this->mediaAttributes());

        $this->expectException(QueryException::class);

        $profile->delete();
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function mediaAttributes(string $name = 'image'): array
    {
        return [
            'disk' => 'media',
            'original_path' => "originals/{$name}.jpg",
            'display_path' => "display/{$name}.jpg",
            'thumbnail_path' => "thumbnails/{$name}.jpg",
            'mime_type' => 'image/jpeg',
            'size_bytes' => 123456,
            'width' => 1920,
            'height' => 1080,
        ];
    }
}
