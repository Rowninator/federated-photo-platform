<?php

namespace App\Actions;

use App\Models\Profile;
use App\Services\FollowingProfileIdsCache;
use Illuminate\Support\Facades\DB;

class UnfollowProfile
{
    public function __construct(private FollowingProfileIdsCache $followingProfileIds) {}

    /**
     * @return array{following: false, requested: false}
     */
    public function handle(Profile $follower, Profile $target): array
    {
        $followDeleted = false;

        $state = DB::transaction(function () use ($follower, $target, &$followDeleted): array {
            $pair = ['followed_profile_id' => $target->id];

            $followDeleted = $follower->outgoingFollows()->where($pair)->delete() > 0;
            $follower->outgoingFollowRequests()->where($pair)->delete();

            return ['following' => false, 'requested' => false];
        });

        if ($followDeleted) {
            $this->followingProfileIds->forget($follower);
        }

        return $state;
    }
}
