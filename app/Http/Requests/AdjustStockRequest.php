<?php

namespace App\Http\Requests;

use App\Services\StockService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(StockService::MANUAL_TYPES)],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
