<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CreatePostTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_posts(): void
    {
        $media = $this->createMedia($this->createProfile('alice'));

        $this->postJson('/posts', ['media_ids' => [$media->id]])->assertUnauthorized();

        $this->assertDatabaseCount('statuses', 0);
        $this->assertNull($media->fresh()->status_id);
    }

    public function test_user_can_create_one_photo_status_without_trusting_extra_fields(): void
    {
        $profile = $this->createProfile('alice');
        $media = $this->createMedia($profile);

        $response = $this->actingAs($profile->user)->postJson('/posts', [
            'media_ids' => [$media->id],
            'profile_id' => 999,
            'in_reply_to_id' => 999,
            'reblog_of_id' => 999,
        ])->assertCreated();

        $status = Status::findOrFail($response->json('id'));
        $this->assertTrue($status->profile->is($profile));
        $this->assertNull($status->caption);
        $this->assertNull($status->in_reply_to_id);
        $this->assertNull($status->reblog_of_id);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'status_id' => $status->id, 'position' => 0]);
        $response->assertExactJson([
            'id' => $status->id,
            'profile_id' => $profile->id,
            'caption' => null,
            'media' => [['id' => $media->id, 'position' => 0]],
            'created_at' => $status->created_at->toISOString(),
        ]);
    }

    public function test_four_photo_album_preserves_request_order_and_accepts_500_character_caption(): void
    {
        $profile = $this->createProfile('alice');
        $ids = array_map(fn () => $this->createMedia($profile)->id, range(1, 4));
        $ids = [$ids[2], $ids[0], $ids[3], $ids[1]];
        $caption = str_repeat('a', 500);

        $response = $this->actingAs($profile->user)->postJson('/posts', [
            'media_ids' => $ids,
            'caption' => $caption,
        ])->assertCreated()->assertJsonPath('caption', $caption);

        $this->assertSame($ids, array_column($response->json('media'), 'id'));
        foreach ($ids as $position => $id) {
            $this->assertDatabaseHas('media', ['id' => $id, 'status_id' => $response->json('id'), 'position' => $position]);
            $response->assertJsonPath('media.'.$position.'.position', $position);
        }
        $this->assertDatabaseCount('statuses', 1);
    }

    public function test_invalid_media_lists_and_overlong_captions_are_rejected(): void
    {
        $profile = $this->createProfile('alice');
        $ids = array_map(fn () => $this->createMedia($profile)->id, range(1, 5));
        $this->actingAs($profile->user);

        $cases = [
            [[], 'media_ids'],
            [['media_ids' => []], 'media_ids'],
            [['media_ids' => 'not-an-array'], 'media_ids'],
            [['media_ids' => $ids], 'media_ids'],
            [['media_ids' => [$ids[0], (string) $ids[0]]], 'media_ids.0'],
            [['media_ids' => ['not-an-id']], 'media_ids.0'],
            [['media_ids' => [$ids[0]], 'caption' => str_repeat('a', 501)], 'caption'],
        ];

        foreach ($cases as [$input, $error]) {
            $this->postJson('/posts', $input)->assertUnprocessable()->assertJsonValidationErrors($error);
        }

        $this->assertDatabaseCount('statuses', 0);
        $this->assertSame(0, Media::whereNotNull('status_id')->count());
        $this->assertSame(0, Media::whereNotNull('position')->count());
    }

    public function test_missing_foreign_and_attached_media_reject_the_entire_request(): void
    {
        $profile = $this->createProfile('alice');
        $available = $this->createMedia($profile);
        $foreign = $this->createMedia($this->createProfile('bob'));
        $attached = $this->createMedia($profile);
        $existingStatus = $profile->statuses()->create([]);
        $attached->update(['status_id' => $existingStatus->id, 'position' => 0]);
        $this->actingAs($profile->user);

        foreach ([999, $foreign->id, $attached->id] as $unavailableId) {
            $this->postJson('/posts', ['media_ids' => [$available->id, $unavailableId]])
                ->assertUnprocessable()->assertJsonValidationErrors('media_ids');

            $this->assertDatabaseCount('statuses', 1);
            $this->assertNull($available->fresh()->status_id);
            $this->assertNull($available->fresh()->position);
            $this->assertNull($foreign->fresh()->status_id);
            $this->assertDatabaseHas('media', ['id' => $attached->id, 'status_id' => $existingStatus->id, 'position' => 0]);
        }
    }

    public function test_failure_after_attachment_updates_rolls_back_status_and_all_attachments(): void
    {
        $profile = $this->createProfile('alice');
        $first = $this->createMedia($profile);
        $second = $this->createMedia($profile);
        Event::listen('eloquent.updated: '.Media::class, function (Media $media) use ($second): void {
            if ($media->id === $second->id) {
                $this->assertSame(2, Media::whereNotNull('status_id')->count());

                throw new RuntimeException('Simulated attachment failure.');
            }
        });

        $this->actingAs($profile->user)->postJson('/posts', ['media_ids' => [$first->id, $second->id]])
            ->assertInternalServerError();

        $this->assertDatabaseCount('statuses', 0);
        foreach ([$first, $second] as $media) {
            $this->assertNull($media->fresh()->status_id);
            $this->assertNull($media->fresh()->position);
        }
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create(['user_id' => User::factory()->create()->id, 'username' => $username]);
    }

    private function createMedia(Profile $profile): Media
    {
        $directory = (string) Str::uuid();

        return $profile->media()->create([
            'disk' => 'media',
            'original_path' => $directory.'/original.jpg',
            'display_path' => $directory.'/display.jpg',
            'thumbnail_path' => $directory.'/thumbnail.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12345,
            'width' => 800,
            'height' => 600,
        ]);
    }
}
