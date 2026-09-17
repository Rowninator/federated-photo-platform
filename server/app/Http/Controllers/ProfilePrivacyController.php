<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfilePrivacyRequest;
use Illuminate\Http\JsonResponse;

class ProfilePrivacyController extends Controller
{
    public function __invoke(UpdateProfilePrivacyRequest $request): JsonResponse
    {
        $profile = $request->user()->profile()->firstOrFail();
        $profile->update([
            'is_private' => $request->boolean('is_private'),
        ]);

        return response()->json([
            'is_private' => $profile->is_private,
        ]);
    }
}
