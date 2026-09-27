<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;
use Throwable;

class ProcessMediaVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $mediaId)
    {
        $this->onQueue('media');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $media = Media::find($this->mediaId);

        if ($media === null || $media->processing_status === Media::PROCESSING_READY) {
            return;
        }

        $media->update([
            'processing_status' => Media::PROCESSING_PROCESSING,
            'processed_at' => null,
            'processing_error' => null,
        ]);

        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->original_path)) {
            throw new RuntimeException('Media original is missing.');
        }

        $image = Image::read($disk->get($media->original_path))->orient();
        $display = (clone $image)->scaleDown(1920, 1920)
            ->encodeByMediaType($media->mime_type, strip: true);

        if (! $disk->put($media->display_path, (string) $display, 'private')) {
            throw new RuntimeException('Unable to store media display variant.');
        }

        $thumbnail = $image->cover(400, 400, 'center')
            ->encodeByMediaType($media->mime_type, strip: true);

        if (! $disk->put($media->thumbnail_path, (string) $thumbnail, 'private')) {
            throw new RuntimeException('Unable to store media thumbnail.');
        }

        $media->update([
            'processing_status' => Media::PROCESSING_READY,
            'processed_at' => now(),
            'processing_error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $media = Media::find($this->mediaId);

        if ($media === null || $media->processing_status === Media::PROCESSING_READY) {
            return;
        }

        $disk = Storage::disk($media->disk);

        try {
            if (! $disk->delete([$media->display_path, $media->thumbnail_path])) {
                report(new RuntimeException('Unable to clean up failed media variants.'));
            }
        } catch (Throwable $cleanupException) {
            report($cleanupException);
        }

        $media->update([
            'processing_status' => Media::PROCESSING_FAILED,
            'processed_at' => null,
            'processing_error' => Str::limit(
                $exception?->getMessage() ?? 'Media variant processing failed.',
                1000,
                '',
            ),
        ]);
    }
}
