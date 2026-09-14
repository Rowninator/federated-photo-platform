<?php

namespace App\Http\Controllers;

use App\Actions\StoreMediaUpload;
use App\Http\Requests\UploadMediaRequest;
use Illuminate\Http\JsonResponse;

class UploadMediaController extends Controller
{
    public function __invoke(UploadMediaRequest $request, StoreMediaUpload $storeMediaUpload): JsonResponse
    {
        $media = $storeMediaUpload->handle(
            $request->user()->profile()->firstOrFail(),
            $request->validated('image'),
        );

        return response()->json($media->only([
            'id', 'mime_type', 'size_bytes', 'width', 'height',
        ]), 201);
    }
}
