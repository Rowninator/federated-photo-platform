<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimelineRequest;
use App\Http\Resources\TimelineStatusResource;
use App\Queries\PublicTimelineQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicTimelineController extends Controller
{
    public function __invoke(TimelineRequest $request, PublicTimelineQuery $timeline): AnonymousResourceCollection
    {
        $statuses = $timeline
            ->query()
            ->cursorPaginate($request->limit())
            ->withQueryString();

        return TimelineStatusResource::collection($statuses);
    }
}
