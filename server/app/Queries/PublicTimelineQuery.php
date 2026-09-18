<?php

namespace App\Queries;

use App\Models\Status;
use Illuminate\Database\Eloquent\Builder;

class PublicTimelineQuery
{
    /**
     * @return Builder<Status>
     */
    public function query(): Builder
    {
        return Status::query()
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
