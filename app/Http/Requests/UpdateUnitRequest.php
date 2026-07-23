<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'name')->ignore($unitId),
            ],
            'symbol' => [
                'required',
                'string',
                'max:50',
                Rule::unique('units', 'symbol')->ignore($unitId),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
