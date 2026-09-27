<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShowMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_inspect_media(): void
    {
        $media = $this->createMedia(User::factory()->create());

        $this->getJson('/media/'.$media->id)->assertUnauthorized();
    }

    public function test_another_profile_cannot_inspect_the_owners_media(): void
    {
        $media = $this->createMedia(User::factory()->create());
        $otherUser = User::factory()->create();
        $otherUser->profile()->create(['username' => 'other']);

        $this->actingAs($otherUser)->getJson('/media/'.$media->id)->assertForbidden();
    }

    public function test_owner_receives_only_public_processing_information(): void
    {
        $owner = User::factory()->create();
        $processedAt = Carbon::parse('2026-09-25 14:30:00 UTC');
        $media = $this->createMedia($owner, Media::PROCESSING_READY, $processedAt);

        $response = $this->actingAs($owner)->getJson('/media/'.$media->id);

        $response->assertOk()->assertExactJson([
            'id' => $media->id,
            'processing_status' => Media::PROCESSING_READY,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 123456,
            'width' => 1920,
            'height' => 1080,
            'processed_at' => $processedAt->toISOString(),
        ]);
        $response->assertJsonMissingPath('disk');
        $response->assertJsonMissingPath('original_path');
        $response->assertJsonMissingPath('display_path');
        $response->assertJsonMissingPath('thumbnail_path');
        $response->assertJsonMissingPath('processing_error');
    }

    public function test_owner_sees_each_persisted_non_ready_state_with_no_processed_timestamp(): void
    {
        $owner = User::factory()->create();

        foreach ([Media::PROCESSING_PENDING, Media::PROCESSING_PROCESSING, Media::PROCESSING_FAILED] as $status) {
            $media = $this->createMedia($owner, $status);

            $this->actingAs($owner)->getJson('/media/'.$media->id)
                ->assertOk()
                ->assertJsonPath('processing_status', $status)
                ->assertJsonPath('processed_at', null);
        }
    }

    public function test_missing_media_returns_not_found(): void
    {
        $owner = User::factory()->create();
        $owner->profile()->create(['username' => 'owner']);

        $this->actingAs($owner)->getJson('/media/999999')->assertNotFound();
    }

    private function createMedia(
        User $owner,
        string $processingStatus = Media::PROCESSING_PENDING,
        ?Carbon $processedAt = null,
    ): Media {
        $profile = $owner->profile()->firstOrCreate([], ['username' => 'owner'.$owner->id]);

        return $profile->media()->create([
            'disk' => 'media',
            'original_path' => 'originals/image.jpg',
            'display_path' => 'display/image.jpg',
            'thumbnail_path' => 'thumbnails/image.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 123456,
            'width' => 1920,
            'height' => 1080,
            'processing_status' => $processingStatus,
            'processed_at' => $processedAt,
            'processing_error' => $processingStatus === Media::PROCESSING_FAILED ? 'internal failure details' : null,
        ]);
    }
}
