<?php

namespace App\Http\Resources;

use App\Models\Media;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Status
 */
class TimelineStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caption' => $this->caption,
            'created_at' => $this->created_at->toISOString(),
            'profile' => [
                'id' => $this->profile->id,
                'username' => $this->profile->username,
                'display_name' => $this->profile->display_name,
            ],
            'media' => $this->media->map(fn (Media $media): array => [
                'id' => $media->id,
                'mime_type' => $media->mime_type,
                'width' => $media->width,
                'height' => $media->height,
                'position' => $media->position,
            ])->values()->all(),
            'counts' => [
                'likes' => $this->likes_count,
                'replies' => $this->replies_count,
                'reposts' => $this->reposts_count,
            ],
        ];
    }
}
