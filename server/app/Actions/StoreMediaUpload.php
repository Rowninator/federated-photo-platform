<?php

namespace App\Actions;

use App\Jobs\ProcessMediaVariants;
use App\Models\Media;
use App\Models\Profile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;
use Throwable;

class StoreMediaUpload
{
    public function handle(Profile $profile, UploadedFile $upload): Media
    {
        $image = Image::read($upload)->orient();
        $width = $image->width();
        $height = $image->height();
        $mimeType = $image->origin()->mediaType();
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        };

        $disk = Storage::disk('media');
        $directory = $profile->id.'/'.Str::uuid();
        $originalPath = $directory.'/original.'.$extension;
        $displayPath = $directory.'/display.'.$extension;
        $thumbnailPath = $directory.'/thumbnail.'.$extension;
        $mediaId = null;

        try {
            if ($disk->putFileAs($directory, $upload, 'original.'.$extension, 'private') === false) {
                throw new RuntimeException('Unable to store media original.');
            }

            return DB::transaction(function () use (
                $profile,
                $originalPath,
                $displayPath,
                $thumbnailPath,
                $mimeType,
                $upload,
                $width,
                $height,
                &$mediaId,
            ): Media {
                $media = $profile->media()->create([
                    'disk' => 'media',
                    'original_path' => $originalPath,
                    'display_path' => $displayPath,
                    'thumbnail_path' => $thumbnailPath,
                    'mime_type' => $mimeType,
                    'size_bytes' => $upload->getSize(),
                    'width' => $width,
                    'height' => $height,
                    'processing_status' => Media::PROCESSING_PENDING,
                ]);
                $mediaId = $media->id;

                ProcessMediaVariants::dispatch($media->id)->afterCommit();

                return $media;
            });
        } catch (Throwable $exception) {
            // A queue push can fail after commit; never remove an original owned by a persisted row.
            $mediaPersisted = $mediaId !== null && Media::query()->whereKey($mediaId)->exists();

            // The database cannot roll back files; this directory belongs only to this upload.
            if (! $mediaPersisted) {
                try {
                    if (! $disk->deleteDirectory($directory)) {
                        report(new RuntimeException('Unable to clean up failed media upload.'));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }
}
