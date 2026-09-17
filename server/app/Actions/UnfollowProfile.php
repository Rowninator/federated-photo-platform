<?php

namespace App\Actions;

use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class UnfollowProfile
{
    /**
     * @return array{following: false, requested: false}
     */
    public function handle(Profile $follower, Profile $target): array
    {
        return DB::transaction(function () use ($follower, $target): array {
            $pair = ['followed_profile_id' => $target->id];

            $follower->outgoingFollows()->where($pair)->delete();
            $follower->outgoingFollowRequests()->where($pair)->delete();

            return ['following' => false, 'requested' => false];
        });
    }
}
