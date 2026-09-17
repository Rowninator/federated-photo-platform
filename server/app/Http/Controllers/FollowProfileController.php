<?php

namespace App\Http\Controllers;

use App\Actions\FollowProfile;
use App\Actions\UnfollowProfile;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowProfileController extends Controller
{
    public function __invoke(Request $request, Profile $profile, FollowProfile $followProfile): JsonResponse
    {
        $state = $followProfile->handle(
            $request->user()->profile()->firstOrFail(),
            $profile,
        );

        return response()->json($state);
    }

    public function destroy(Request $request, Profile $profile, UnfollowProfile $unfollowProfile): JsonResponse
    {
        $state = $unfollowProfile->handle(
            $request->user()->profile()->firstOrFail(),
            $profile,
        );

        return response()->json($state);
    }
}
