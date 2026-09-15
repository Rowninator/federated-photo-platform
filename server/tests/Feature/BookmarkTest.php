<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookmarking_and_unbookmarking_are_idempotent(): void
    {
        $profile = $this->createProfile('alice');
        $status = $profile->statuses()->create([]);
        $url = '/posts/'.$status->id.'/bookmark';
        $this->actingAs($profile->user);

        $this->postJson($url)->assertNoContent();
        $bookmark = Bookmark::sole();
        $this->postJson($url)->assertNoContent();

        $this->assertDatabaseCount('bookmarks', 1);
        $this->assertSame($bookmark->id, Bookmark::sole()->id);
        $this->assertTrue($bookmark->profile->is($profile));
        $this->assertTrue($bookmark->status->is($status));
        $this->assertSame([$bookmark->id], $profile->bookmarks->modelKeys());
        $this->assertSame([$bookmark->id], $status->bookmarks->modelKeys());

        $this->deleteJson($url)->assertNoContent();
        $this->assertDatabaseCount('bookmarks', 0);
        $this->deleteJson($url)->assertNoContent();
        $this->assertDatabaseCount('bookmarks', 0);
    }

    public function test_profiles_bookmark_independently_without_changing_public_post_json(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $target = $alice->statuses()->create([]);
        $other = $alice->statuses()->create([]);
        $alice->likes()->create(['status_id' => $target->id]);
        $postUrl = '/posts/'.$target->id;
        $url = $postUrl.'/bookmark';
        $publicJson = $this->getJson($postUrl)->assertOk()->json();

        $this->actingAs($alice->user)->postJson($url, ['profile_id' => $bob->id])->assertNoContent();
        $this->postJson('/posts/'.$other->id.'/bookmark')->assertNoContent();
        $this->getJson($postUrl)->assertOk()->assertExactJson($publicJson);
        $this->actingAs($bob->user)->postJson($url)->assertNoContent();
        $this->getJson($postUrl)->assertOk()->assertExactJson($publicJson);
        $this->assertDatabaseCount('bookmarks', 3);

        $this->actingAs($alice->user)->deleteJson($url, ['profile_id' => $bob->id])->assertNoContent();

        $this->assertDatabaseCount('bookmarks', 2);
        $this->assertDatabaseHas('bookmarks', ['profile_id' => $bob->id, 'status_id' => $target->id]);
        $this->assertDatabaseHas('bookmarks', ['profile_id' => $alice->id, 'status_id' => $other->id]);
        Auth::logout();
        $this->getJson($postUrl)->assertOk()->assertExactJson($publicJson);
    }

    public function test_guests_cannot_bookmark_or_unbookmark(): void
    {
        $profile = $this->createProfile('alice');
        $status = $profile->statuses()->create([]);
        $bookmark = $profile->bookmarks()->create(['status_id' => $status->id]);
        $url = '/posts/'.$status->id.'/bookmark';

        $this->postJson($url)->assertUnauthorized();
        $this->deleteJson($url)->assertUnauthorized();

        $this->assertDatabaseCount('bookmarks', 1);
        $this->assertModelExists($bookmark);
    }

    public function test_database_rejects_duplicate_profile_status_pairs(): void
    {
        $profile = $this->createProfile('alice');
        $status = $profile->statuses()->create([]);
        $attributes = ['profile_id' => $profile->id, 'status_id' => $status->id];
        DB::table('bookmarks')->insert($attributes);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('bookmarks')->insert($attributes);
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create(['user_id' => User::factory()->create()->id, 'username' => $username]);
    }
}
