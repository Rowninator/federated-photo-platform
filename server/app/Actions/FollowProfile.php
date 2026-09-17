<?php

namespace App\Actions;

use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowProfile
{
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

        return DB::transaction(function () use ($follower, $target): array {
            $target = Profile::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $pair = ['followed_profile_id' => $target->id];

            if (! $target->is_private) {
                $follower->outgoingFollowRequests()->where($pair)->delete();
                $follower->outgoingFollows()->firstOrCreate($pair);

                return ['following' => true, 'requested' => false];
            }

            if ($follower->outgoingFollows()->where($pair)->exists()) {
                $follower->outgoingFollowRequests()->where($pair)->delete();

                return ['following' => true, 'requested' => false];
            }

            $follower->outgoingFollowRequests()->firstOrCreate($pair);

            return ['following' => false, 'requested' => true];
        });
    }
}
