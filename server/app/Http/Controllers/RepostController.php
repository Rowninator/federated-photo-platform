<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RepostController extends Controller
{
    public function store(Request $request, Status $status): Response
    {
        abort_unless($status->in_reply_to_id === null && $status->reblog_of_id === null, 404);

        $profile = $request->user()->profile()->firstOrFail();
        $profile->statuses()->firstOrCreate(
            ['reblog_of_id' => $status->id],
            ['caption' => null, 'in_reply_to_id' => null],
        );

        return response()->noContent();
    }

    public function destroy(Request $request, Status $status): Response
    {
        abort_unless($status->in_reply_to_id === null && $status->reblog_of_id === null, 404);

        $profile = $request->user()->profile()->firstOrFail();
        $profile->statuses()
            ->where('reblog_of_id', $status->id)
            ->whereNull('in_reply_to_id')
            ->delete();

        return response()->noContent();
    }
}
