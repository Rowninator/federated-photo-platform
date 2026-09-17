<?php

namespace App\Actions;

use App\Models\Follow;
use App\Models\FollowRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class AcceptFollowRequest
{
    /**
     * @return array{following: true, requested: false}
     */
    public function handle(User $user, FollowRequest $followRequest): array
    {
        return DB::transaction(function () use ($user, $followRequest): array {
            $followRequest = FollowRequest::query()
                ->whereKey($followRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($user)->authorize('decide', $followRequest);

            Follow::query()->firstOrCreate([
                'follower_profile_id' => $followRequest->follower_profile_id,
                'followed_profile_id' => $followRequest->followed_profile_id,
            ]);

            if (! $followRequest->delete()) {
                throw new RuntimeException('Unable to delete accepted follow request.');
            }

            return ['following' => true, 'requested' => false];
        });
    }
}
