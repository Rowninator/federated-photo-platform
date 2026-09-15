<?php

namespace Tests\Feature;

use App\Models\Like;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_liking_and_unliking_are_idempotent(): void
    {
        $profile = $this->createProfile('alice');
        $status = $profile->statuses()->create([]);
        $url = '/posts/'.$status->id.'/like';
        $this->actingAs($profile->user);

        $this->postJson($url)->assertNoContent();
        $like = Like::sole();
        $this->postJson($url)->assertNoContent();

        $this->assertDatabaseCount('likes', 1);
        $this->assertSame($like->id, Like::sole()->id);
        $this->assertTrue($like->profile->is($profile));
        $this->assertTrue($like->status->is($status));
        $this->assertSame([$like->id], $profile->likes->modelKeys());
        $this->assertSame([$like->id], $status->likes->modelKeys());
        $this->assertNotNull($like->created_at);
        $this->assertNotNull($like->updated_at);

        $this->deleteJson($url)->assertNoContent();
        $this->assertDatabaseCount('likes', 0);
        $this->deleteJson($url)->assertNoContent();
        $this->assertDatabaseCount('likes', 0);
        $this->getJson('/posts/'.$status->id)->assertOk()->assertJsonPath('counts.likes', 0);
    }

    public function test_profiles_like_independently_and_post_detail_reports_only_the_target_count(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $target = $alice->statuses()->create([]);
        $other = $alice->statuses()->create([]);
        $url = '/posts/'.$target->id.'/like';

        $this->actingAs($alice->user)->postJson($url, ['profile_id' => $bob->id])->assertNoContent();
        $this->postJson('/posts/'.$other->id.'/like')->assertNoContent();
        $this->actingAs($bob->user)->postJson($url)->assertNoContent();

        $this->assertDatabaseCount('likes', 3);
        $this->getJson('/posts/'.$target->id)->assertOk()
            ->assertJsonPath('counts.likes', 2)->assertJsonMissingPath('likes');

        $this->actingAs($alice->user)->deleteJson($url, ['profile_id' => $bob->id])->assertNoContent();

        $this->assertDatabaseCount('likes', 2);
        $this->assertDatabaseHas('likes', ['profile_id' => $bob->id, 'status_id' => $target->id]);
        $this->assertDatabaseHas('likes', ['profile_id' => $alice->id, 'status_id' => $other->id]);
        $this->getJson('/posts/'.$target->id)->assertOk()->assertJsonPath('counts.likes', 1);
    }

    public function test_guests_cannot_like_or_unlike(): void
    {
        $profile = $this->createProfile('alice');
        $status = $profile->statuses()->create([]);
        $like = $profile->likes()->create(['status_id' => $status->id]);
        $url = '/posts/'.$status->id.'/like';

        $this->postJson($url)->assertUnauthorized();
        $this->deleteJson($url)->assertUnauthorized();

        $this->assertDatabaseCount('likes', 1);
        $this->assertModelExists($like);
    }

    public function test_database_rejects_duplicate_profile_status_pairs(): void
    {
        $profile = $this->createProfile('alice');
        $status = $profile->statuses()->create([]);
        $attributes = ['profile_id' => $profile->id, 'status_id' => $status->id];
        DB::table('likes')->insert($attributes);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('likes')->insert($attributes);
    }

    public function test_existing_reply_and_repost_statuses_can_be_liked_but_missing_statuses_cannot(): void
    {
        $profile = $this->createProfile('alice');
        $original = $profile->statuses()->create([]);
        $reply = $profile->statuses()->create(['in_reply_to_id' => $original->id]);
        $repost = $profile->statuses()->create(['reblog_of_id' => $original->id]);
        $this->actingAs($profile->user);

        foreach ([$reply, $repost] as $status) {
            $this->postJson('/posts/'.$status->id.'/like')->assertNoContent();
            $this->assertDatabaseHas('likes', ['profile_id' => $profile->id, 'status_id' => $status->id]);
        }

        $this->postJson('/posts/999/like')->assertNotFound();
        $this->deleteJson('/posts/999/like')->assertNotFound();
        $this->assertDatabaseCount('likes', 2);
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create(['user_id' => User::factory()->create()->id, 'username' => $username]);
    }
}
