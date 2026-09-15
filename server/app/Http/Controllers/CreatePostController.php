<?php

namespace App\Http\Controllers;

use App\Actions\CreatePhotoStatus;
use App\Http\Requests\CreatePostRequest;
use App\Models\Media;
use Illuminate\Http\JsonResponse;

class CreatePostController extends Controller
{
    public function __invoke(CreatePostRequest $request, CreatePhotoStatus $createPhotoStatus): JsonResponse
    {
        $status = $createPhotoStatus->handle(
            $request->user()->profile()->firstOrFail(),
            $request->validated('media_ids'),
            $request->validated('caption'),
        );

        return response()->json([
            'id' => $status->id,
            'profile_id' => $status->profile_id,
            'caption' => $status->caption,
            'media' => $status->media->map(fn (Media $media): array => $media->only(['id', 'position']))->values(),
            'created_at' => $status->created_at->toISOString(),
        ], 201);
    }
}
