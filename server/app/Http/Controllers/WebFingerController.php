<?php

namespace App\Http\Controllers;

use App\Federation\LocalFederationIdentity;
use App\Http\Requests\WebFingerRequest;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;

class WebFingerController extends Controller
{
    public function __invoke(WebFingerRequest $request): JsonResponse
    {
        abort_unless(
            strcasecmp($request->acctDomain(), (string) config('federation.domain')) === 0,
            404,
        );

        $profile = Profile::query()
            ->where('username', Profile::normalizeUsername($request->acctUsername()))
            ->firstOrFail();
        $identity = LocalFederationIdentity::fromProfile($profile);

        return response()->json([
            'subject' => $identity->acct(),
            'links' => [[
                'rel' => 'self',
                'type' => 'application/activity+json',
                'href' => $identity->actorUrl(),
            ]],
        ], 200, [
            'Content-Type' => 'application/jrd+json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
