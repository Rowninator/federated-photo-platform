<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bookmark extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['profile_id', 'status_id'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
