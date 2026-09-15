<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'caption' => ['required', 'string', 'max:500'],
            'media_ids' => ['prohibited'],
            'image' => ['prohibited'],
        ];
    }
}
