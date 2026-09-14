<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class UploadMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    public function test_guests_cannot_upload_media(): void
    {
        $this->postJson('/media', [
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertUnauthorized();
    }

    public function test_jpeg_and_png_uploads_store_owned_metadata_and_private_variants(): void
    {
        $user = $this->signInWithProfile();
        $disk = Storage::disk('media');

        foreach ([['jpg', 2400, 1600, 1920, 1280], ['png', 1600, 2400, 1280, 1920], ['jpg', 640, 480, 640, 480], ['png', 480, 640, 480, 640]] as [$extension, $width, $height, $displayWidth, $displayHeight]) {
            $source = UploadedFile::fake()->image("photo.{$extension}", $width, $height);
            // A real UploadedFile checks content, independently of client metadata.
            $image = new UploadedFile($source->getPathname(), 'upload.bin', 'application/octet-stream', UPLOAD_ERR_OK, true);

            $response = $this->postJson('/media', ['image' => $image])->assertCreated();
            $media = Media::findOrFail($response->json('id'));
            $mimeType = $extension === 'jpg' ? 'image/jpeg' : 'image/png';

            $this->assertTrue($media->profile->is($user->profile));
            $this->assertSame('media', $media->disk);
            $response->assertExactJson([
                'id' => $media->id,
                'mime_type' => $mimeType,
                'size_bytes' => $image->getSize(),
                'width' => $width,
                'height' => $height,
            ]);
            $this->assertDatabaseHas('media', [
                'id' => $media->id,
                'profile_id' => $user->profile->id,
                'mime_type' => $mimeType,
                'size_bytes' => $image->getSize(),
                'width' => $width,
                'height' => $height,
            ]);

            foreach (['original', 'display', 'thumbnail'] as $variant) {
                $path = $media->{$variant.'_path'};
                $this->assertMatchesRegularExpression('#^'.$user->profile->id.'/[0-9a-f-]{36}/'.$variant.'\\.'.$extension.'$#', $path);
                $disk->assertExists($path);
            }

            $this->assertSame($image->get(), $disk->get($media->original_path));
            $display = getimagesizefromstring($disk->get($media->display_path));
            $thumbnail = getimagesizefromstring($disk->get($media->thumbnail_path));
            $this->assertSame([$displayWidth, $displayHeight, $mimeType], [$display[0], $display[1], $display['mime']]);
            $this->assertSame([400, 400, $mimeType], [$thumbnail[0], $thumbnail[1], $thumbnail['mime']]);
        }

        $this->assertDatabaseCount('media', 4);
        $this->assertCount(12, $disk->allFiles());
    }

    public function test_missing_and_unsuccessful_uploads_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/media')->assertUnprocessable()->assertJsonValidationErrors('image');

        $source = UploadedFile::fake()->image('photo.jpg');
        $image = new UploadedFile($source->getPathname(), 'photo.jpg', 'image/jpeg', UPLOAD_ERR_PARTIAL, true);

        $this->postJson('/media', ['image' => $image])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_non_images_and_unsupported_images_are_rejected_despite_client_metadata(): void
    {
        $this->actingAs(User::factory()->create());

        $sources = [
            UploadedFile::fake()->createWithContent('text.txt', 'This is not an image.'),
            UploadedFile::fake()->image('animation.gif'),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>'),
        ];

        foreach ($sources as $source) {
            $image = new UploadedFile($source->getPathname(), 'photo.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);

            $this->postJson('/media', ['image' => $image])
                ->assertUnprocessable()->assertJsonValidationErrors('image');
        }
    }

    public function test_upload_size_limit_is_inclusive(): void
    {
        $this->signInWithProfile();

        $this->postJson('/media', ['image' => UploadedFile::fake()->image('photo.jpg')->size(15000)])
            ->assertCreated();

        $this->postJson('/media', ['image' => UploadedFile::fake()->image('photo.jpg')->size(15001)])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_width_and_height_limits_are_inclusive(): void
    {
        $this->signInWithProfile();

        foreach ([[12000, 1], [1, 12000]] as [$width, $height]) {
            $this->postJson('/media', ['image' => UploadedFile::fake()->image('photo.png', $width, $height)])
                ->assertCreated();
        }

        foreach ([[12001, 1], [1, 12001]] as [$width, $height]) {
            $this->postJson('/media', ['image' => UploadedFile::fake()->image('photo.png', $width, $height)])
                ->assertUnprocessable()->assertJsonValidationErrors('image');
        }
    }

    public function test_orientation_is_applied_and_derived_metadata_is_stripped(): void
    {
        $this->signInWithProfile();
        $source = UploadedFile::fake()->image('camera.jpg', 800, 400);
        // Minimal EXIF TIFF directory: orientation 6 means rotate 90 degrees clockwise.
        $exif = "Exif\0\0II".pack('vV', 42, 8).pack('v', 1)
            .pack('vvV', 0x0112, 3, 1).pack('v', 6)."\0\0".pack('V', 0);
        $comment = 'private-camera-note';
        $bytes = substr($source->get(), 0, 2)
            ."\xff\xe1".pack('n', strlen($exif) + 2).$exif
            ."\xff\xfe".pack('n', strlen($comment) + 2).$comment
            .substr($source->get(), 2);
        $image = UploadedFile::fake()->createWithContent('camera.jpg', $bytes);

        $response = $this->postJson('/media', ['image' => $image])->assertCreated();
        $media = Media::findOrFail($response->json('id'));
        $this->assertSame([400, 800], [$media->width, $media->height]);
        $disk = Storage::disk('media');
        $this->assertSame($bytes, $disk->get($media->original_path));
        $display = getimagesizefromstring($disk->get($media->display_path));
        $this->assertSame([400, 800], [$display[0], $display[1]]);

        foreach ([$media->display_path, $media->thumbnail_path] as $path) {
            $this->assertStringNotContainsString("Exif\0\0", $disk->get($path));
            $this->assertStringNotContainsString($comment, $disk->get($path));
        }
    }

    public function test_thumbnail_is_center_cropped(): void
    {
        $this->signInWithProfile();
        $canvas = imagecreatetruecolor(1600, 800);
        imagefilledrectangle($canvas, 0, 0, 399, 799, imagecolorallocate($canvas, 255, 0, 0));
        imagefilledrectangle($canvas, 400, 0, 1199, 799, imagecolorallocate($canvas, 0, 255, 0));
        imagefilledrectangle($canvas, 1200, 0, 1599, 799, imagecolorallocate($canvas, 0, 0, 255));
        ob_start();
        imagepng($canvas);
        $image = UploadedFile::fake()->createWithContent('bands.png', ob_get_clean());

        $response = $this->postJson('/media', ['image' => $image])->assertCreated();
        $media = Media::findOrFail($response->json('id'));
        $thumbnail = imagecreatefromstring(Storage::disk('media')->get($media->thumbnail_path));

        foreach ([[0, 0], [399, 399], [200, 200]] as [$x, $y]) {
            $this->assertSame(0x00FF00, imagecolorat($thumbnail, $x, $y));
        }
    }

    public function test_persistence_failure_rolls_back_the_row_and_cleans_up_only_this_upload(): void
    {
        $this->signInWithProfile();
        $disk = Storage::disk('media');
        $disk->put('existing/original.jpg', 'existing upload');
        Event::listen('eloquent.created: '.Media::class, function () use ($disk): void {
            $this->assertCount(4, $disk->allFiles());

            throw new RuntimeException('Simulated persistence failure.');
        });

        $this->postJson('/media', ['image' => UploadedFile::fake()->image('photo.jpg', 800, 600)])
            ->assertInternalServerError();

        $this->assertDatabaseCount('media', 0);
        $this->assertSame(['existing/original.jpg'], $disk->allFiles());
    }

    public function test_failed_variant_write_cleans_up_the_original(): void
    {
        $this->signInWithProfile();
        $disk = Storage::disk('media');
        $failingDisk = Mockery::mock($disk);
        $failingDisk->shouldReceive('put')->once()->andReturnUsing(function () use ($disk): bool {
            $this->assertCount(1, $disk->allFiles());

            return false;
        });
        Storage::set('media', $failingDisk);

        $this->postJson('/media', ['image' => UploadedFile::fake()->image('photo.jpg', 800, 600)])
            ->assertInternalServerError();

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], $disk->allFiles());
    }

    private function signInWithProfile(): User
    {
        $user = User::factory()->create();
        $user->profile()->create(['username' => 'alice']);
        $this->actingAs($user);

        return $user;
    }
}
