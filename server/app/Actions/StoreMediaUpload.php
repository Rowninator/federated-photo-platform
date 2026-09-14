<?php

namespace App\Actions;

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

        try {
            if ($disk->putFileAs($directory, $upload, 'original.'.$extension, 'private') === false) {
                throw new RuntimeException('Unable to store media original.');
            }

            // GD re-encodes pixels without copying embedded source metadata.
            $display = (clone $image)->scaleDown(1920, 1920)->encodeByMediaType($mimeType, strip: true);
            if (! $disk->put($displayPath, (string) $display, 'private')) {
                throw new RuntimeException('Unable to store media display variant.');
            }

            $thumbnail = $image->cover(400, 400, 'center')->encodeByMediaType($mimeType, strip: true);
            if (! $disk->put($thumbnailPath, (string) $thumbnail, 'private')) {
                throw new RuntimeException('Unable to store media thumbnail.');
            }

            return DB::transaction(fn () => $profile->media()->create([
                'disk' => 'media',
                'original_path' => $originalPath,
                'display_path' => $displayPath,
                'thumbnail_path' => $thumbnailPath,
                'mime_type' => $mimeType,
                'size_bytes' => $upload->getSize(),
                'width' => $width,
                'height' => $height,
            ]));
        } catch (Throwable $exception) {
            // The database cannot roll back files; this directory belongs only to this upload.
            try {
                if (! $disk->deleteDirectory($directory)) {
                    report(new RuntimeException('Unable to clean up failed media upload.'));
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw $exception;
        }
    }
}
