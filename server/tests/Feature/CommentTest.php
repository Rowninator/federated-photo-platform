<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_text_only_comment_on_a_top_level_post(): void
    {
        $author = $this->createProfile('alice');
        $commenter = $this->createProfile('bob');
        $post = $author->statuses()->create([]);
        $caption = str_repeat('a', 500);

        $response = $this->actingAs($commenter->user)->postJson('/posts/'.$post->id.'/comments', [
            'caption' => $caption,
            'profile_id' => $author->id,
            'in_reply_to_id' => 999,
            'reblog_of_id' => $post->id,
        ])->assertCreated();

        $comment = Status::findOrFail($response->json('id'));
        $this->assertTrue($comment->profile->is($commenter));
        $this->assertTrue($comment->parent->is($post));
        $this->assertNull($comment->reblog_of_id);
        $this->assertCount(0, $comment->media);
        $this->assertDatabaseCount('media', 0);
        $response->assertExactJson([
            'id' => $comment->id,
            'caption' => $caption,
            'profile' => ['username' => 'bob', 'display_name' => 'Bob'],
            'in_reply_to_id' => $post->id,
            'created_at' => $comment->created_at->toISOString(),
        ]);
        $this->getJson('/posts/'.$post->id)->assertOk()->assertJsonPath('counts.replies', 1);
    }

    public function test_invalid_captions_and_media_attachments_are_rejected(): void
    {
        $profile = $this->createProfile('alice');
        $post = $profile->statuses()->create([]);
        $this->actingAs($profile->user);
        $cases = [
            [[], 'caption'],
            [['caption' => null], 'caption'],
            [['caption' => ''], 'caption'],
            [['caption' => '   '], 'caption'],
            [['caption' => ['not text']], 'caption'],
            [['caption' => str_repeat('a', 501)], 'caption'],
            [['caption' => 'Hello', 'media_ids' => [999]], 'media_ids'],
            [['caption' => 'Hello', 'image' => UploadedFile::fake()->image('photo.jpg')], 'image'],
        ];

        foreach ($cases as [$input, $error]) {
            $this->postJson('/posts/'.$post->id.'/comments', $input)
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }

        $this->assertDatabaseCount('statuses', 1);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_guests_cannot_create_or_delete_comments(): void
    {
        $profile = $this->createProfile('alice');
        $post = $profile->statuses()->create([]);
        $comment = $profile->statuses()->create(['caption' => 'Hello', 'in_reply_to_id' => $post->id]);

        $this->postJson('/posts/'.$post->id.'/comments', ['caption' => 'Hello'])->assertUnauthorized();
        $this->deleteJson('/comments/'.$comment->id)->assertUnauthorized();

        $this->assertDatabaseCount('statuses', 2);
        $this->assertModelExists($comment);
    }

    public function test_only_comment_owner_can_delete_it_without_deleting_the_parent_or_other_comments(): void
    {
        $author = $this->createProfile('alice');
        $commenter = $this->createProfile('bob');
        $post = $author->statuses()->create([]);
        $comment = $commenter->statuses()->create(['caption' => 'Hello', 'in_reply_to_id' => $post->id]);
        $other = $commenter->statuses()->create(['caption' => 'Another reply', 'in_reply_to_id' => $post->id]);
        $like = $author->likes()->create(['status_id' => $comment->id]);
        $bookmark = $author->bookmarks()->create(['status_id' => $comment->id]);
        $parentLike = $author->likes()->create(['status_id' => $post->id]);
        $parentBookmark = $author->bookmarks()->create(['status_id' => $post->id]);

        $this->actingAs($author->user)->deleteJson('/comments/'.$comment->id)->assertForbidden();
        $this->assertModelExists($comment);
        $this->actingAs($commenter->user)->deleteJson('/comments/'.$comment->id)->assertNoContent();

        $this->assertModelMissing($comment);
        $this->assertModelMissing($like);
        $this->assertModelMissing($bookmark);
        $this->assertModelExists($post);
        $this->assertModelExists($other);
        $this->assertModelExists($parentLike);
        $this->assertModelExists($parentBookmark);
        $this->getJson('/posts/'.$post->id)->assertOk()->assertJsonPath('counts.replies', 1);
    }

    public function test_comment_endpoints_reject_inappropriate_status_types(): void
    {
        $profile = $this->createProfile('alice');
        $post = $profile->statuses()->create([]);
        $reply = $profile->statuses()->create(['caption' => 'Hello', 'in_reply_to_id' => $post->id]);
        $repost = $profile->statuses()->create(['reblog_of_id' => $post->id]);
        $this->actingAs($profile->user);

        foreach ([$reply, $repost] as $target) {
            $this->postJson('/posts/'.$target->id.'/comments', ['caption' => 'Not allowed'])->assertNotFound();
        }

        foreach ([$post, $repost] as $target) {
            $this->deleteJson('/comments/'.$target->id)->assertNotFound();
            $this->assertModelExists($target);
        }

        $this->assertDatabaseCount('statuses', 3);
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
