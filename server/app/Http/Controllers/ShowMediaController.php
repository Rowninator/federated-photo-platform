<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ShowMediaController extends Controller
{
    public function __invoke(Media $media): JsonResponse
    {
        Gate::authorize('view', $media);

        return response()->json([
            'id' => $media->id,
            'processing_status' => $media->processing_status,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
            'width' => $media->width,
            'height' => $media->height,
            'processed_at' => $media->processed_at?->toISOString(),
        ]);
    }
}
