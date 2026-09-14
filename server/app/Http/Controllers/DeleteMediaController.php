<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteMediaController extends Controller
{
    public function __invoke(Media $media): Response
    {
        Gate::authorize('delete', $media);

        $disk = Storage::disk($media->disk);

        foreach ([$media->original_path, $media->display_path, $media->thumbnail_path] as $path) {
            if (! $disk->delete($path)) {
                throw new RuntimeException('Unable to delete media file.');
            }
        }

        // Keep the row until all file deletions succeed, allowing failed requests to be retried.
        if (! $media->delete()) {
            throw new RuntimeException('Unable to delete media record.');
        }

        return response()->noContent();
    }
}
