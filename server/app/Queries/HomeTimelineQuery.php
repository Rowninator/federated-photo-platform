<?php

namespace App\Queries;

use App\Models\Follow;
use App\Models\Profile;
use App\Models\Status;
use Illuminate\Database\Eloquent\Builder;

class HomeTimelineQuery
{
    /**
     * @return Builder<Status>
     */
    public function for(Profile $profile): Builder
    {
        $followedProfileIds = Follow::query()
            ->select('followed_profile_id')
            ->where('follower_profile_id', $profile->id);

        return Status::query()
            ->where(function (Builder $query) use ($profile, $followedProfileIds): void {
                $query->where('profile_id', $profile->id)
                    ->orWhereIn('profile_id', $followedProfileIds);
            })
            ->whereNull('in_reply_to_id')
            ->whereNull('reblog_of_id')
            ->with([
                'profile:id,username,display_name',
                'media' => fn ($query) => $query
                    ->select('id', 'status_id', 'mime_type', 'width', 'height', 'position')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->withCount(['likes', 'replies', 'reposts'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
