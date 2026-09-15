<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepostTest extends TestCase
{
    use RefreshDatabase;

    public function test_reposting_is_idempotent_and_keeps_content_on_the_original(): void
    {
        $author = $this->createProfile('alice');
        $reposter = $this->createProfile('bob');
        $original = $author->statuses()->create(['caption' => 'Canonical content']);
        $media = $original->media()->create([
            'profile_id' => $author->id,
            'position' => 0,
            'disk' => 'media',
            'original_path' => 'original.jpg',
            'display_path' => 'display.jpg',
            'thumbnail_path' => 'thumbnail.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12345,
            'width' => 800,
            'height' => 600,
        ]);
        $url = '/posts/'.$original->id.'/repost';
        $this->actingAs($reposter->user);

        $this->postJson($url, ['caption' => 'Ignored', 'media_ids' => [$media->id]])->assertNoContent();
        $repost = Status::query()->where('profile_id', $reposter->id)->where('reblog_of_id', $original->id)->sole();
        $this->postJson($url)->assertNoContent();

        $this->assertDatabaseCount('statuses', 2);
        $this->assertSame($repost->id, Status::query()->where('reblog_of_id', $original->id)->sole()->id);
        $this->assertNull($repost->caption);
        $this->assertNull($repost->in_reply_to_id);
        $this->assertSame($original->id, $repost->reblog_of_id);
        $this->assertTrue($repost->profile->is($reposter));
        $this->assertTrue($repost->reblogOf->is($original));
        $this->assertSame([$repost->id], $original->reposts->modelKeys());
        $this->assertCount(0, $repost->media);
        $this->assertTrue($media->fresh()->status->is($original));
        $this->getJson('/posts/'.$original->id)->assertOk()->assertJsonPath('counts.reposts', 1);
    }

    public function test_profiles_repost_independently_and_unrepost_is_scoped_and_idempotent(): void
    {
        $author = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $carol = $this->createProfile('carol');
        $original = $author->statuses()->create([]);
        $url = '/posts/'.$original->id.'/repost';

        $this->actingAs($bob->user)->postJson($url)->assertNoContent();
        $this->actingAs($carol->user)->postJson($url)->assertNoContent();
        $this->assertSame(2, $original->reposts()->count());

        $this->actingAs($bob->user)->deleteJson($url)->assertNoContent();
        $this->deleteJson($url)->assertNoContent();

        $this->assertModelExists($original);
        $this->assertDatabaseMissing('statuses', ['profile_id' => $bob->id, 'reblog_of_id' => $original->id]);
        $this->assertDatabaseHas('statuses', ['profile_id' => $carol->id, 'reblog_of_id' => $original->id]);
        $this->getJson('/posts/'.$original->id)->assertOk()->assertJsonPath('counts.reposts', 1);
    }

    public function test_reply_and_repost_targets_are_rejected(): void
    {
        $profile = $this->createProfile('alice');
        $original = $profile->statuses()->create([]);
        $reply = $profile->statuses()->create(['caption' => 'Reply', 'in_reply_to_id' => $original->id]);
        $repost = $profile->statuses()->create(['reblog_of_id' => $original->id]);
        $this->actingAs($profile->user);

        foreach ([$reply, $repost] as $target) {
            $url = '/posts/'.$target->id.'/repost';
            $this->postJson($url)->assertNotFound();
            $this->deleteJson($url)->assertNotFound();
            $this->assertModelExists($target);
        }

        $this->assertDatabaseCount('statuses', 3);
    }

    public function test_guests_cannot_repost_or_unrepost(): void
    {
        $author = $this->createProfile('alice');
        $reposter = $this->createProfile('bob');
        $original = $author->statuses()->create([]);
        $repost = $reposter->statuses()->create(['reblog_of_id' => $original->id]);
        $url = '/posts/'.$original->id.'/repost';

        $this->postJson($url)->assertUnauthorized();
        $this->deleteJson($url)->assertUnauthorized();

        $this->assertModelExists($original);
        $this->assertModelExists($repost);
    }

    public function test_database_rejects_duplicate_profile_original_pairs(): void
    {
        $author = $this->createProfile('alice');
        $reposter = $this->createProfile('bob');
        $original = $author->statuses()->create([]);
        $attributes = [
            'profile_id' => $reposter->id,
            'reblog_of_id' => $original->id,
        ];
        DB::table('statuses')->insert($attributes);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('statuses')->insert($attributes);
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => $username,
            'display_name' => ucfirst($username),
        ]);
    }
}
