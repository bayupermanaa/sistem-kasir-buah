<?php

namespace App\Http\Requests;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(Asset::categories())],
            'purchase_date' => ['required', 'date'],
            'purchase_price' => ['required', 'numeric', 'gt:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(Asset::paymentMethods()))],
            'useful_life_years' => ['nullable', 'integer', 'min:1'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(Asset::statuses())],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $purchasePrice = (float) $this->input('purchase_price', 0);
            $quantity = (int) $this->input('quantity', 0);
            $residualValue = (float) ($this->input('residual_value') ?? 0);
            $totalCost = round($purchasePrice * $quantity, 2);

            if ($residualValue > $totalCost) {
                $validator->errors()->add('residual_value', 'Nilai residu tidak boleh melebihi total biaya aset.');
            }
        });
    }
}
