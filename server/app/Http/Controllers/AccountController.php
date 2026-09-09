<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'username' => $profile->username,
            'display_name' => $profile->display_name,
            'bio' => $profile->bio,
        ]);
    }
}
