<?php

namespace App\Http\Controllers;

use App\Actions\RegisterUser;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['status' => 'ok'], 201);
    }
}
