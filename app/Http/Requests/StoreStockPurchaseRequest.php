<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\DecimalMath;

class StoreStockPurchaseRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'owner'; }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'purchase_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'qris', 'transfer', 'credit'])],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('payment_method') !== 'credit') {
                return;
            }

            if (! $this->filled('supplier_id')) {
                $validator->errors()->add('supplier_id', 'Supplier wajib dipilih untuk pembelian kredit.');
            }
            $unitCost = $this->input('unit_cost');
            $unitCostValue = is_numeric($unitCost) ? DecimalMath::parse($unitCost, 2) : 0;
            if (! $this->filled('unit_cost') || $unitCostValue <= 0) {
                $validator->errors()->add('unit_cost', 'Harga beli wajib diisi untuk pembelian kredit.');
            }
            if (! $this->filled('due_date')) {
                $validator->errors()->add('due_date', 'Tanggal jatuh tempo wajib diisi untuk pembelian kredit.');
            }
        });
    }
}
