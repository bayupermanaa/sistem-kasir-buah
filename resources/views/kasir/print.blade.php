<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk {{ $transaction->invoice_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            color: #000;
            font-size: 12px;
        }

        .receipt-shell {
            max-width: 80mm;
            margin: 1rem auto;
            background: #fff;
            padding: 12px;
            border: 1px solid #dee2e6;
        }

        .receipt-title {
            font-size: 16px;
            font-weight: 700;
            text-align: center;
        }

        .receipt-subtitle,
        .receipt-meta,
        .receipt-footer {
            text-align: center;
            font-size: 11px;
        }

        .receipt-divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        .receipt-table {
            width: 100%;
            font-size: 11px;
        }

        .receipt-table td,
        .receipt-table th {
            padding: 2px 0;
            vertical-align: top;
        }

        .receipt-actions {
            max-width: 80mm;
            margin: 0 auto 1rem;
            display: flex;
            gap: .5rem;
        }

        @media print {
            body {
                background: #fff;
            }

            .receipt-actions {
                display: none !important;
            }

            .receipt-shell {
                border: 0;
                margin: 0;
                max-width: 80mm;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    @php
        $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    @endphp

    <div class="receipt-actions pt-3">
        <button type="button" class="btn btn-dark btn-sm flex-grow-1" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Cetak
        </button>
        <a href="{{ route('kasir.show', $transaction) }}" class="btn btn-outline-secondary btn-sm flex-grow-1">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <div class="receipt-shell">
        <div class="receipt-title">Sistem Kasir</div>
        <div class="receipt-subtitle">Toko Buah Teras Fatimah</div>
        <div class="receipt-meta mt-1">
            Toko: {{ $transaction->outlet?->name }}<br>
            Invoice: {{ $transaction->invoice_number }}<br>
            {{ $transaction->created_at->format('d/m/Y H:i') }}<br>
            Kasir: {{ $transaction->user?->name }}
        </div>

        <div class="receipt-divider"></div>

        <table class="receipt-table">
            <tbody>
                @foreach ($transaction->items as $item)
                    <tr>
                        <td colspan="2">
                            <div class="fw-semibold">{{ $item->product?->name }}</div>
                            <div>
                                {{ number_format((float) $item->quantity, 3, ',', '.') }} kg
                                @if ($item->weight_grams)
                                    ({{ number_format((float) $item->weight_grams, 0, ',', '.') }} g)
                                @endif
                            </div>
                            <div>{{ $formatRupiah($item->price_per_unit) }}/kg</div>
                        </td>
                    </tr>
                    <tr>
                        <td>Subtotal</td>
                        <td class="text-end">{{ $formatRupiah($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="receipt-divider"></div>

        <table class="receipt-table">
            <tr>
                <td>Subtotal</td>
                <td class="text-end">{{ $formatRupiah($transaction->subtotal) }}</td>
            </tr>
            <tr>
                <td>Diskon</td>
                <td class="text-end">{{ $formatRupiah($transaction->discount) }}</td>
            </tr>
            <tr>
                <td class="fw-semibold">Total</td>
                <td class="text-end fw-semibold">{{ $formatRupiah($transaction->total) }}</td>
            </tr>
            <tr>
                <td>Uang diterima</td>
                <td class="text-end">{{ $formatRupiah($transaction->cash_received ?? 0) }}</td>
            </tr>
            <tr>
                <td>Kembalian</td>
                <td class="text-end">{{ $formatRupiah($transaction->change_amount ?? 0) }}</td>
            </tr>
        </table>

        <div class="receipt-divider"></div>
        <div class="receipt-footer">
            Terima kasih atas kunjungan Anda
        </div>
    </div>
</body>
</html>
