<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_profiles_can_have_multiple_ordinary_statuses_with_nullable_fields(): void
    {
        $profile = $this->createProfile('alice');
        $first = $profile->statuses()->create([])->fresh();
        $second = $profile->statuses()->create(['caption' => 'A local photo']);
        $this->createProfile('bob')->statuses()->create([]);

        $this->assertTrue($first->profile->is($profile));
        $this->assertNull($first->caption);
        $this->assertNull($first->in_reply_to_id);
        $this->assertNull($first->reblog_of_id);
        $this->assertNotNull($first->created_at);
        $this->assertNotNull($first->updated_at);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $profile->statuses->modelKeys());
        $this->assertDatabaseHas('statuses', ['id' => $second->id, 'caption' => 'A local photo']);
    }

    public function test_replies_and_reposts_have_distinct_bidirectional_relationships(): void
    {
        $profile = $this->createProfile('alice');
        $original = $profile->statuses()->create([]);
        $reply = $profile->statuses()->create(['caption' => 'A text reply', 'in_reply_to_id' => $original->id]);
        $repost = $profile->statuses()->create(['reblog_of_id' => $original->id]);
        $other = $profile->statuses()->create([]);
        $profile->statuses()->create(['in_reply_to_id' => $other->id]);
        $profile->statuses()->create(['reblog_of_id' => $other->id]);

        $this->assertTrue($reply->parent->is($original));
        $this->assertTrue($repost->reblogOf->is($original));
        $this->assertSame([$reply->id], $original->replies->modelKeys());
        $this->assertSame([$repost->id], $original->reposts->modelKeys());
        $this->assertCount(0, $reply->media);
    }

    public function test_different_profiles_can_repost_the_same_status(): void
    {
        $alice = $this->createProfile('alice');
        $bob = $this->createProfile('bob');
        $original = $alice->statuses()->create([]);

        $alice->statuses()->create(['reblog_of_id' => $original->id]);
        $bob->statuses()->create(['reblog_of_id' => $original->id]);

        $this->assertCount(2, $original->reposts);
    }

    public function test_duplicate_reposts_are_rejected_by_the_database(): void
    {
        $profile = $this->createProfile('alice');
        $original = $profile->statuses()->create([]);
        $profile->statuses()->create(['reblog_of_id' => $original->id]);

        $this->expectException(QueryException::class);

        Status::query()->insert(['profile_id' => $profile->id, 'reblog_of_id' => $original->id]);
    }

    #[DataProvider('foreignKeyColumns')]
    public function test_foreign_keys_require_existing_records(string $column): void
    {
        $profile = $this->createProfile('alice');

        $this->expectException(QueryException::class);

        Status::create(['profile_id' => $profile->id, $column => 999]);
    }

    #[DataProvider('foreignKeyColumns')]
    public function test_referenced_records_cannot_be_deleted(string $column): void
    {
        $profile = $this->createProfile('alice');
        $original = $profile->statuses()->create([]);
        if ($column !== 'profile_id') {
            $profile->statuses()->create([$column => $original->id]);
        }

        $this->expectException(QueryException::class);

        ($column === 'profile_id' ? $profile : $original)->delete();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foreignKeyColumns(): array
    {
        return [
            'profile' => ['profile_id'],
            'reply parent' => ['in_reply_to_id'],
            'reposted status' => ['reblog_of_id'],
        ];
    }

    private function createProfile(string $username): Profile
    {
        return Profile::create(['user_id' => User::factory()->create()->id, 'username' => $username]);
    }
}
