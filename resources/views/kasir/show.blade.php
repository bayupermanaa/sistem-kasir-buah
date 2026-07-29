<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Transaksi Selesai',
            'subtitle' => 'Ringkasan transaksi tunai yang baru disimpan.',
            'icon' => 'bi-receipt-cutoff',
        ])
    </x-slot>

    @php
        $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    @endphp

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card ui-table-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                        <div>
                            <div class="text-muted small">Invoice</div>
                            <div class="fw-bold fs-4">{{ $transaction->invoice_number }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Tanggal</div>
                            <div class="fw-semibold">{{ $transaction->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Kasir</div>
                            <div class="fw-semibold">{{ $transaction->user?->name }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Toko</div>
                            <div class="fw-semibold">{{ $transaction->outlet?->name }}</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-end">Berat</th>
                                    <th class="text-end">Harga</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transaction->items as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->product?->name }}</div>
                                            <div class="text-muted small">{{ $item->product?->category?->name }} &middot; {{ $item->product?->unit?->name }}</div>
                                        </td>
                                        <td class="text-end">{{ number_format((float) $item->quantity, 3, ',', '.') }} kg ({{ number_format((float) $item->weight_grams, 0, ',', '.') }} g)</td>
                                        <td class="text-end">{{ $formatRupiah($item->price_per_unit) }}/kg</td>
                                        <td class="text-end fw-semibold">{{ $formatRupiah($item->subtotal) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card ui-stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-semibold">{{ $formatRupiah($transaction->subtotal) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Diskon</span>
                        <span class="fw-semibold">{{ $formatRupiah($transaction->discount) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total</span>
                        <span class="fw-bold fs-5">{{ $formatRupiah($transaction->total) }}</span>
                    </div>
                    <hr class="ui-soft-divider">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Uang diterima</span>
                        <span class="fw-semibold">{{ $formatRupiah($transaction->cash_received ?? 0) }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Kembalian</span>
                        <span class="fw-semibold text-success">{{ $formatRupiah($transaction->change_amount ?? 0) }}</span>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 pt-0">
                    <div class="d-grid gap-2 d-sm-flex">
                        <a href="{{ route('kasir.print', $transaction) }}" target="_blank" class="btn btn-dark flex-grow-1">
                            <i class="bi bi-printer me-1"></i>Cetak Struk
                        </a>
                        <a href="{{ route('kasir') }}" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-arrow-left me-1"></i>Kembali ke Kasir
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
