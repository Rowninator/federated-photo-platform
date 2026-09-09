<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'username',
        'display_name',
        'bio',
    ];

    /**
     * Get the validation rules for a canonical local username.
     *
     * @return list<string>
     */
    public static function usernameRules(): array
    {
        return [
            'required',
            'string',
            'max:30',
            'regex:/\A[a-z0-9_.-]+\z/',
            'unique:profiles,username',
        ];
    }

    public static function normalizeUsername(string $username): string
    {
        return strtolower($username);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeUsername($value),
        );
    }
}
