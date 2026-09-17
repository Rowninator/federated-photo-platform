<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'username',
        'display_name',
        'bio',
        'is_private',
    ];

    /**
     * Get the validation rules for a canonical local username.
     *
     * @return list<string>
     */
    public static function usernameRules(): array
    {
        return [
            'required',
            'string',
            'max:30',
            'regex:/\A[a-z0-9_.-]+\z/',
            'unique:profiles,username',
        ];
    }

    public static function normalizeUsername(string $username): string
    {
        return strtolower($username);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function outgoingFollows(): HasMany
    {
        return $this->hasMany(Follow::class, 'follower_profile_id');
    }

    public function incomingFollows(): HasMany
    {
        return $this->hasMany(Follow::class, 'followed_profile_id');
    }

    public function followingProfiles(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_profile_id', 'followed_profile_id')
            ->withTimestamps();
    }

    public function followerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followed_profile_id', 'follower_profile_id')
            ->withTimestamps();
    }

    public function outgoingFollowRequests(): HasMany
    {
        return $this->hasMany(FollowRequest::class, 'follower_profile_id');
    }

    public function incomingFollowRequests(): HasMany
    {
        return $this->hasMany(FollowRequest::class, 'followed_profile_id');
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeUsername($value),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }
}
