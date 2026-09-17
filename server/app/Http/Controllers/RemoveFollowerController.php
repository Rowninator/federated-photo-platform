<?php

namespace App\Http\Controllers;

use App\Actions\RemoveFollower;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoveFollowerController extends Controller
{
    public function __invoke(Request $request, Profile $profile, RemoveFollower $removeFollower): JsonResponse
    {
        $state = $removeFollower->handle(
            $request->user()->profile()->firstOrFail(),
            $profile,
        );

        return response()->json($state);
    }
}
