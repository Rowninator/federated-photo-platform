<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookmarkController extends Controller
{
    public function store(Request $request, Status $status): Response
    {
        $profile = $request->user()->profile()->firstOrFail();
        $profile->bookmarks()->firstOrCreate(['status_id' => $status->id]);

        return response()->noContent();
    }

    public function destroy(Request $request, Status $status): Response
    {
        $profile = $request->user()->profile()->firstOrFail();
        $profile->bookmarks()->where('status_id', $status->id)->delete();

        return response()->noContent();
    }
}
