<?php

namespace App\Actions;

use App\Models\Follow;
use App\Models\FollowRequest;
use App\Models\User;
use App\Services\FollowingProfileIdsCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class AcceptFollowRequest
{
    public function __construct(private FollowingProfileIdsCache $followingProfileIds) {}

    /**
     * @return array{following: true, requested: false}
     */
    public function handle(User $user, FollowRequest $followRequest): array
    {
        $followerProfileId = $followRequest->follower_profile_id;
        $followCreated = false;

        $state = DB::transaction(function () use ($user, $followRequest, &$followCreated): array {
            $followRequest = FollowRequest::query()
                ->whereKey($followRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($user)->authorize('decide', $followRequest);

            $follow = Follow::query()->firstOrCreate([
                'follower_profile_id' => $followRequest->follower_profile_id,
                'followed_profile_id' => $followRequest->followed_profile_id,
            ]);
            $followCreated = $follow->wasRecentlyCreated;

            if (! $followRequest->delete()) {
                throw new RuntimeException('Unable to delete accepted follow request.');
            }

            return ['following' => true, 'requested' => false];
        });

        if ($followCreated) {
            $this->followingProfileIds->forget($followerProfileId);
        }

        return $state;
    }
}
