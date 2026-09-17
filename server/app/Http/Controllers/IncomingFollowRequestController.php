<?php

namespace App\Http\Controllers;

use App\Models\FollowRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomingFollowRequestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $profile = $request->user()->profile()->firstOrFail();
        $requests = $profile->incomingFollowRequests()
            ->with('followerProfile:id,username,display_name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'requests' => $requests->map(fn (FollowRequest $followRequest): array => [
                'id' => $followRequest->id,
                'requester' => $followRequest->followerProfile->only([
                    'id', 'username', 'display_name',
                ]),
                'created_at' => $followRequest->created_at->toISOString(),
            ])->values(),
        ]);
    }
}
