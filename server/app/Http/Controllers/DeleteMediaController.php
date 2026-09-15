<?php

namespace App\Http\Controllers;

use App\Actions\DeleteMedia;
use App\Models\Media;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeleteMediaController extends Controller
{
    public function __invoke(Media $media, DeleteMedia $deleteMedia): Response
    {
        Gate::authorize('delete', $media);

        $deleteMedia->handle($media);

        return response()->noContent();
    }
}
