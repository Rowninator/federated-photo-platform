<?php

namespace App\Actions;

use App\Models\Status;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeletePost
{
    public function __construct(private DeleteMedia $deleteMedia) {}

    public function handle(Status $status): void
    {
        DB::transaction(function () use ($status): void {
            $post = Status::query()->whereKey($status->id)->lockForUpdate()->firstOrFail();
            $post->load('media');

            foreach ($post->media as $media) {
                $this->deleteMedia->handle($media);
            }

            // Remove referencing rows before the original to satisfy the RESTRICT self references.
            $post->replies()->delete();
            $post->reposts()->delete();

            if (! $post->delete()) {
                throw new RuntimeException('Unable to delete post.');
            }
        });
    }
}
