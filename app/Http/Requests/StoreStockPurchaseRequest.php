<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockPurchaseRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'owner'; }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'purchase_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'qris', 'transfer', 'other'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}