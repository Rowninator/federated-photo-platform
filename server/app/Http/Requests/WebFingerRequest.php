<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WebFingerRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'resource' => [
                'required',
                'string',
                'regex:/\Aacct:[^@\s]+@[^@\s]+\z/i',
            ],
        ];
    }

    public function acctUsername(): string
    {
        return $this->acctParts()[0];
    }

    public function acctDomain(): string
    {
        return $this->acctParts()[1];
    }

    /**
     * @return array{string, string}
     */
    private function acctParts(): array
    {
        $resource = (string) $this->validated('resource');

        return explode('@', substr($resource, 5), 2);
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The resource must be a valid acct URI.',
        ], 400));
    }
}
