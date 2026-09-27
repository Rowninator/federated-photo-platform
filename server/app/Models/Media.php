<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    public const PROCESSING_PENDING = 'pending';

    public const PROCESSING_PROCESSING = 'processing';

    public const PROCESSING_READY = 'ready';

    public const PROCESSING_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'status_id',
        'position',
        'disk',
        'original_path',
        'display_path',
        'thumbnail_path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'processing_status',
        'processed_at',
        'processing_error',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
