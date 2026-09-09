<?php

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'username' => Profile::usernameRules(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('username'))) {
            $normalized['username'] = Profile::normalizeUsername($this->input('username'));
        }

        if (is_string($this->input('email'))) {
            $normalized['email'] = strtolower($this->input('email'));
        }

        $this->merge($normalized);
    }
}
