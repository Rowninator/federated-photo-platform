<?php

namespace App\Policies;

use App\Models\Status;
use App\Models\User;

class StatusPolicy
{
    public function deletePost(User $user, Status $status): bool
    {
        return $user->profile()->whereKey($status->profile_id)->exists();
    }

    public function deleteComment(User $user, Status $status): bool
    {
        return $user->profile()->whereKey($status->profile_id)->exists();
    }
}
