<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimelineRequest;
use App\Http\Resources\TimelineStatusResource;
use App\Queries\HomeTimelineQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HomeTimelineController extends Controller
{
    public function __invoke(TimelineRequest $request, HomeTimelineQuery $timeline): AnonymousResourceCollection
    {
        $statuses = $timeline
            ->for($request->user()->profile()->firstOrFail())
            ->cursorPaginate($request->limit())
            ->withQueryString();

        return TimelineStatusResource::collection($statuses);
    }
}
