<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunSingleActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                Rule::in(array_keys((array) config('actions.actions', []))),
            ],
            'input' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
