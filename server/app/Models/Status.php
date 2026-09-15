<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'caption',
        'in_reply_to_id',
        'reblog_of_id',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'in_reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'in_reply_to_id');
    }

    public function reblogOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reblog_of_id');
    }

    public function reposts(): HasMany
    {
        return $this->hasMany(self::class, 'reblog_of_id');
    }
}
