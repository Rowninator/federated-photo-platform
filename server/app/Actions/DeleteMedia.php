<?php

namespace App\Actions;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteMedia
{
    public function handle(Media $media): void
    {
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
    }
}
