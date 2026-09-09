<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    /**
     * @param  array{username: string, email: string, password: string}  $attributes
     */
    public function __invoke(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::create([
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ]);

            $user->profile()->create([
                'username' => $attributes['username'],
            ]);

            return $user;
        });
    }
}
