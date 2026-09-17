<?php

namespace App\Http\Controllers;

use App\Actions\AcceptFollowRequest;
use App\Models\FollowRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class FollowRequestController extends Controller
{
    public function accept(
        Request $request,
        FollowRequest $followRequest,
        AcceptFollowRequest $acceptFollowRequest,
    ): JsonResponse {
        return response()->json(
            $acceptFollowRequest->handle($request->user(), $followRequest),
        );
    }

    public function destroy(Request $request, FollowRequest $followRequest): JsonResponse
    {
        Gate::forUser($request->user())->authorize('decide', $followRequest);

        if (! $followRequest->delete()) {
            throw new RuntimeException('Unable to reject follow request.');
        }

        return response()->json([
            'following' => false,
            'requested' => false,
        ]);
    }
}
