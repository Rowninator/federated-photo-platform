<?php

namespace App\Actions;

use App\Models\Profile;
use App\Services\FollowingProfileIdsCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowProfile
{
    public function __construct(private FollowingProfileIdsCache $followingProfileIds) {}

    /**
     * @return array{following: bool, requested: bool}
     */
    public function handle(Profile $follower, Profile $target): array
    {
        if ($follower->is($target)) {
            throw ValidationException::withMessages([
                'profile' => 'You cannot follow your own profile.',
            ]);
        }

        $followCreated = false;

        $state = DB::transaction(function () use ($follower, $target, &$followCreated): array {
            $target = Profile::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $pair = ['followed_profile_id' => $target->id];

            if (! $target->is_private) {
                $follower->outgoingFollowRequests()->where($pair)->delete();
                $follow = $follower->outgoingFollows()->firstOrCreate($pair);
                $followCreated = $follow->wasRecentlyCreated;

                return ['following' => true, 'requested' => false];
            }

            if ($follower->outgoingFollows()->where($pair)->exists()) {
                $follower->outgoingFollowRequests()->where($pair)->delete();

                return ['following' => true, 'requested' => false];
            }

            $follower->outgoingFollowRequests()->firstOrCreate($pair);

            return ['following' => false, 'requested' => true];
        });

        if ($followCreated) {
            $this->followingProfileIds->forget($follower);
        }

        return $state;
    }
}
