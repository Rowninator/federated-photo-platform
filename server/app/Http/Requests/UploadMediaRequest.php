<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'bail',
                'required',
                'file',
                'max:15000',
                'image',
                'mimes:jpg,jpeg,png',
                'dimensions:max_width=12000,max_height=12000',
            ],
        ];
    }
}
