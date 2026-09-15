<?php

namespace App\Http\Controllers;

use App\Actions\DeletePost;
use App\Models\Status;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeletePostController extends Controller
{
    public function __invoke(Status $status, DeletePost $deletePost): Response
    {
        abort_unless($status->in_reply_to_id === null && $status->reblog_of_id === null, 404);
        Gate::authorize('deletePost', $status);

        $deletePost->handle($status);

        return response()->noContent();
    }
}
