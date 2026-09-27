<?php

namespace App\Services;

use App\Models\Follow;
use App\Models\Profile;
use Illuminate\Support\Facades\Cache;

class FollowingProfileIdsCache
{
    private const TTL_SECONDS = 300;

    /**
     * @return list<int>
     */
    public function get(Profile $profile): array
    {
        return Cache::remember(
            $this->key($profile),
            self::TTL_SECONDS,
            fn (): array => Follow::query()
                ->where('follower_profile_id', $profile->id)
                ->orderBy('followed_profile_id')
                ->pluck('followed_profile_id')
                ->map(fn (int|string $id): int => (int) $id)
                ->all(),
        );
    }

    public function forget(Profile|int $profile): void
    {
        Cache::forget($this->key($profile));
    }

    public function key(Profile|int $profile): string
    {
        $profileId = $profile instanceof Profile ? $profile->id : $profile;

        return "profiles:{$profileId}:following-profile-ids:v1";
    }
}
