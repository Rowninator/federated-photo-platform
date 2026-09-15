<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Status;
use Illuminate\Http\JsonResponse;

class ShowPostController extends Controller
{
    public function __invoke(string $status): JsonResponse
    {
        $post = Status::query()
            ->whereKey($status)
            ->whereNull('in_reply_to_id')
            ->whereNull('reblog_of_id')
            ->with([
                'profile:id,username,display_name',
                'media' => fn ($query) => $query
                    ->select('id', 'status_id', 'mime_type', 'width', 'height')
                    ->orderBy('position'),
            ])
            ->withCount(['likes', 'replies', 'reposts'])
            ->firstOrFail();

        return response()->json([
            'id' => $post->id,
            'caption' => $post->caption,
            'created_at' => $post->created_at->toISOString(),
            'profile' => $post->profile->only(['username', 'display_name']),
            'media' => $post->media->map(fn (Media $media): array => $media->only([
                'id', 'mime_type', 'width', 'height',
            ]))->values(),
            'counts' => [
                'likes' => $post->likes_count,
                'replies' => $post->replies_count,
                'reposts' => $post->reposts_count,
            ],
        ]);
    }
}
