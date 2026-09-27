<?php

namespace App\Actions;

use App\Models\Profile;
use App\Services\FollowingProfileIdsCache;

class RemoveFollower
{
    public function __construct(private FollowingProfileIdsCache $followingProfileIds) {}

    /**
     * @return array{follower: false}
     */
    public function handle(Profile $profile, Profile $follower): array
    {
        $followDeleted = $profile->incomingFollows()
            ->where('follower_profile_id', $follower->id)
            ->delete() > 0;

        if ($followDeleted) {
            $this->followingProfileIds->forget($follower);
        }

        return ['follower' => false];
    }
}
