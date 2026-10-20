<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'cutover_date' => ['required', 'date'],
            'cash_balance' => ['nullable', 'numeric', 'min:0'],
            'bank_balance' => ['nullable', 'numeric', 'min:0'],
            'inventory_balance' => ['nullable', 'numeric', 'min:0'],
            'fixed_asset_balance' => ['nullable', 'numeric', 'min:0'],
            'liabilities_balance' => ['nullable', 'numeric', 'min:0'],
            'capital_balance' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
