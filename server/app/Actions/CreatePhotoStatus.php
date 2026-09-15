<?php

namespace App\Actions;

use App\Models\Media;
use App\Models\Profile;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreatePhotoStatus
{
    /**
     * @param  list<int|string>  $mediaIds
     */
    public function handle(Profile $profile, array $mediaIds, ?string $caption): Status
    {
        return DB::transaction(function () use ($profile, $mediaIds, $caption): Status {
            // Lock in a stable order, independently of the requested display order.
            $media = Media::query()->whereIn('id', $mediaIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            // Check all candidate rows under the lock before creating or attaching anything.
            if ($media->count() !== count($mediaIds) || $media->contains(
                fn (Media $item): bool => (string) $item->profile_id !== (string) $profile->id || $item->status_id !== null
            )) {
                throw ValidationException::withMessages([
                    'media_ids' => 'One or more selected images are unavailable.',
                ]);
            }

            $status = $profile->statuses()->create([
                'caption' => $caption,
                'in_reply_to_id' => null,
                'reblog_of_id' => null,
            ]);

            foreach (array_values($mediaIds) as $position => $id) {
                if (! $media->get($id)->update(['status_id' => $status->id, 'position' => $position])) {
                    throw new RuntimeException('Unable to attach media to status.');
                }
            }

            return $status->load(['media' => fn ($query) => $query->orderBy('position')]);
        });
    }
}
