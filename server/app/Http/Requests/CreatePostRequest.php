<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:500'],
            'media_ids' => ['required', 'array', 'list', 'min:1', 'max:4'],
            'media_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
