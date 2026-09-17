<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['follower_profile_id', 'followed_profile_id'];

    public function followerProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'follower_profile_id');
    }

    public function followedProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'followed_profile_id');
    }
}
