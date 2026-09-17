<?php

namespace App\Actions;

use App\Models\Profile;

class RemoveFollower
{
    /**
     * @return array{follower: false}
     */
    public function handle(Profile $profile, Profile $follower): array
    {
        $profile->incomingFollows()
            ->where('follower_profile_id', $follower->id)
            ->delete();

        return ['follower' => false];
    }
}
