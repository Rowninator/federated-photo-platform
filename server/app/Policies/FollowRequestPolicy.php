<?php

namespace App\Policies;

use App\Models\FollowRequest;
use App\Models\User;

class FollowRequestPolicy
{
    public function decide(User $user, FollowRequest $followRequest): bool
    {
        $profileId = $user->profile()->value('id');

        return $profileId !== null
            && (string) $profileId === (string) $followRequest->followed_profile_id
            && (string) $profileId !== (string) $followRequest->follower_profile_id;
    }
}
