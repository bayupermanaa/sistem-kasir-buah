<?php

namespace App\Http\Requests;

use App\Models\PayablePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayablePaymentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'owner'; }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', Rule::in(array_keys(PayablePayment::PAYMENT_METHODS))],
            'idempotency_key' => ['required', 'uuid'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
