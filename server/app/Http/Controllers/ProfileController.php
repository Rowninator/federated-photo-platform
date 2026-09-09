<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->profile;

        $profile->update($request->safe()->only([
            'display_name',
            'bio',
        ]));

        return response()->json([
            'username' => $profile->username,
            'display_name' => $profile->display_name,
            'bio' => $profile->bio,
        ]);
    }
}
