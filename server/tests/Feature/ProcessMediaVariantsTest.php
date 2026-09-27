<?php

namespace Tests\Feature;

use App\Jobs\ProcessMediaVariants;
use App\Models\Media;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class ProcessMediaVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    public function test_job_creates_variants_marks_media_ready_and_is_retry_safe(): void
    {
        $profile = $this->createProfile();
        $disk = Storage::disk('media');
        $cases = [
            ['jpg', 2400, 1600, 1920, 1280],
            ['png', 1600, 2400, 1280, 1920],
            ['jpg', 640, 480, 640, 480],
            ['png', 480, 640, 480, 640],
        ];
        $processed = [];

        foreach ($cases as $index => [$extension, $width, $height, $displayWidth, $displayHeight]) {
            $mimeType = $extension === 'jpg' ? 'image/jpeg' : 'image/png';
            $source = UploadedFile::fake()->image("source.{$extension}", $width, $height);
            $media = $this->createMedia($profile, (string) $index, $extension, $mimeType, $width, $height);
            $disk->put($media->original_path, $source->get(), 'private');
            $job = new ProcessMediaVariants($media->id);

            $this->assertSame(3, $job->tries);
            $this->assertSame([10, 60], $job->backoff());
            $this->assertSame('media', $job->queue);

            $job->handle();
            $media->refresh();
            $processed[] = $media;

            $display = getimagesizefromstring($disk->get($media->display_path));
            $thumbnail = getimagesizefromstring($disk->get($media->thumbnail_path));
            $this->assertSame([$displayWidth, $displayHeight, $mimeType], [$display[0], $display[1], $display['mime']]);
            $this->assertSame([400, 400, $mimeType], [$thumbnail[0], $thumbnail[1], $thumbnail['mime']]);
            $this->assertSame(Media::PROCESSING_READY, $media->processing_status);
            $this->assertNotNull($media->processed_at);
            $this->assertNull($media->processing_error);
        }

        $first = $processed[0];
        $displayBeforeRetry = $disk->get($first->display_path);
        (new ProcessMediaVariants($first->id))->handle();

        $this->assertSame($displayBeforeRetry, $disk->get($first->display_path));
        $this->assertDatabaseCount('media', 4);
        $this->assertCount(12, $disk->allFiles());
    }

    public function test_missing_media_is_ignored_and_not_recreated(): void
    {
        $profile = $this->createProfile();
        $media = $this->createMedia($profile, 'deleted', 'jpg', 'image/jpeg', 800, 600);
        $mediaId = $media->id;
        $media->delete();

        (new ProcessMediaVariants($mediaId))->handle();

        $this->assertDatabaseCount('media', 0);
    }

    public function test_retryable_failure_rethrows_and_terminal_failure_retains_original(): void
    {
        $profile = $this->createProfile();
        $media = $this->createMedia($profile, 'failure', 'jpg', 'image/jpeg', 800, 600);
        $disk = Storage::disk('media');
        $disk->put($media->original_path, 'invalid image bytes', 'private');
        $job = new ProcessMediaVariants($media->id);

        try {
            $job->handle();
            $this->fail('The processing exception was not rethrown.');
        } catch (Throwable $exception) {
            $this->assertSame(Media::PROCESSING_PROCESSING, $media->fresh()->processing_status);
            $disk->assertExists($media->original_path);

            $disk->put($media->display_path, 'partial display');
            $disk->put($media->thumbnail_path, 'partial thumbnail');
            $job->failed($exception);
        }

        $media->refresh();
        $this->assertSame(Media::PROCESSING_FAILED, $media->processing_status);
        $this->assertNull($media->processed_at);
        $this->assertNotNull($media->processing_error);
        $this->assertLessThanOrEqual(1000, strlen($media->processing_error));
        $disk->assertExists($media->original_path);
        $disk->assertMissing([$media->display_path, $media->thumbnail_path]);
        $this->assertDatabaseCount('media', 1);
    }

    private function createProfile(): Profile
    {
        return Profile::create([
            'user_id' => User::factory()->create()->id,
            'username' => 'alice',
        ]);
    }

    private function createMedia(
        Profile $profile,
        string $name,
        string $extension,
        string $mimeType,
        int $width,
        int $height,
    ): Media {
        return $profile->media()->create([
            'disk' => 'media',
            'original_path' => "{$name}/original.{$extension}",
            'display_path' => "{$name}/display.{$extension}",
            'thumbnail_path' => "{$name}/thumbnail.{$extension}",
            'mime_type' => $mimeType,
            'size_bytes' => 12345,
            'width' => $width,
            'height' => $height,
        ]);
    }
}
