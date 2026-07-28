<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Riwayat Transaksi',
            'subtitle' => 'Pantau transaksi tunai berdasarkan filter invoice, kasir, tanggal, metode, dan status.',
            'icon' => 'bi-receipt-cutoff',
        ])
    </x-slot>

    @php
        $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Jumlah Transaksi</div>
                    <div class="fs-3 fw-semibold">{{ number_format((int) $summary['transaction_count'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Penjualan</div>
                    <div class="fs-3 fw-semibold">{{ $formatRupiah($summary['total_sales']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Item Terjual</div>
                    <div class="fs-3 fw-semibold">{{ number_format((int) $summary['total_items'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Berat Terjual</div>
                    <div class="fs-3 fw-semibold">{{ number_format((float) $summary['total_weight'], 3, ',', '.') }} kg</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('transactions.index') }}" class="row g-3">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="search">Pencarian invoice</label>
                    <input type="text" id="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Nomor invoice">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="date_from">Dari</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="date_to">Sampai</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
                </div>
                @if (auth()->user()->role === 'owner')
                    <div class="col-md-6 col-lg-2">
                        <label class="form-label" for="cashier_id">Kasir</label>
                        <select id="cashier_id" name="cashier_id" class="form-select">
                            <option value="">Semua kasir</option>
                            @foreach ($cashiers as $cashier)
                                <option value="{{ $cashier->id }}" @selected((string) $filters['cashier_id'] === (string) $cashier->id)>{{ $cashier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="payment_method">Metode</label>
                    <select id="payment_method" name="payment_method" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected($filters['payment_method'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-funnel me-1"></i>Terapkan Filter
                    </button>
                    <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Invoice</th>
                        <th>Tanggal & Jam</th>
                        <th>Kasir</th>
                        <th class="text-end">Jumlah Item</th>
                        <th>Metode</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ ($transactions->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="fw-semibold">{{ $transaction->invoice_number }}</td>
                            <td>{{ $transaction->formatted_created_at }}</td>
                            <td>{{ $transaction->user?->name }}</td>
                            <td class="text-end">{{ number_format((int) $transaction->items_count, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $transaction->payment_method_badge_class }}">{{ $transaction->payment_method_label }}</span>
                            </td>
                            <td class="text-end">{{ $formatRupiah($transaction->total) }}</td>
                            <td>
                                <span class="badge {{ $transaction->status_badge_class }}">{{ $transaction->status_label }}</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('kasir.show', $transaction) }}" class="btn btn-outline-primary">Lihat Detail</a>
                                    <a href="{{ route('kasir.print', $transaction) }}" target="_blank" class="btn btn-outline-dark">Cetak Ulang Struk</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Tidak ada transaksi yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $transactions->links() }}
        </div>
    </div>
</x-app-layout>
