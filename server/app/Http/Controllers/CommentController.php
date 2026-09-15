<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCommentRequest;
use App\Models\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class CommentController extends Controller
{
    public function store(CreateCommentRequest $request, Status $status): JsonResponse
    {
        abort_unless($status->in_reply_to_id === null && $status->reblog_of_id === null, 404);

        $profile = $request->user()->profile()->firstOrFail();
        $comment = $profile->statuses()->create([
            'caption' => $request->validated('caption'),
            'in_reply_to_id' => $status->id,
            'reblog_of_id' => null,
        ]);

        return response()->json([
            'id' => $comment->id,
            'caption' => $comment->caption,
            'profile' => $profile->only(['username', 'display_name']),
            'in_reply_to_id' => $comment->in_reply_to_id,
            'created_at' => $comment->created_at->toISOString(),
        ], 201);
    }

    public function destroy(Status $status): Response
    {
        abort_unless($status->in_reply_to_id !== null && $status->reblog_of_id === null, 404);
        Gate::authorize('deleteComment', $status);

        if (! $status->delete()) {
            throw new RuntimeException('Unable to delete comment.');
        }

        return response()->noContent();
    }
}
