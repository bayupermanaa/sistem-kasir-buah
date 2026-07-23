<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')],
            'symbol' => ['required', 'string', 'max:50', Rule::unique('units', 'symbol')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
