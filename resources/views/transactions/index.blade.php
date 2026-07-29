<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Riwayat Transaksi',
            'subtitle' => 'Pantau transaksi tunai berdasarkan invoice, tanggal, kasir, metode, dan status.',
            'icon' => 'bi-receipt-cutoff',
        ])
    </x-slot>

    @php
        $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Jumlah Transaksi</span>
                        <span class="ui-kpi-icon"><i class="bi bi-receipt"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((int) $summary['transaction_count'], 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Transaksi pada filter aktif</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Total Penjualan</span>
                        <span class="ui-kpi-icon"><i class="bi bi-cash-coin"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ $formatRupiah($summary['total_sales']) }}</div>
                    <div class="ui-summary-note">Omzet transaksi tersaring</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Item Terjual</span>
                        <span class="ui-kpi-icon"><i class="bi bi-box"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((int) $summary['total_items'], 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Total baris item transaksi</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Berat Terjual</span>
                        <span class="ui-kpi-icon"><i class="bi bi-speedometer2"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((float) $summary['total_weight'], 3, ',', '.') }} kg</div>
                    <div class="ui-summary-note">Akumulasi berat item terjual</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ui-surface ui-filter-card p-3 p-lg-4 mb-3">
        <form method="GET" action="{{ route('transactions.index') }}" class="row g-3 align-items-end" data-submit-loading>
            <div class="col-md-6 col-lg-3">
                <label class="form-label fw-semibold" for="search">Pencarian invoice</label>
                <input type="text" id="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Nomor invoice">
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="date_from">Dari</label>
                <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="date_to">Sampai</label>
                <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
            </div>
            @if (auth()->user()->role === 'owner')
                <div class="col-md-6 col-lg-2">
                    <label class="form-label fw-semibold" for="cashier_id">Kasir</label>
                    <select id="cashier_id" name="cashier_id" class="form-select">
                        <option value="">Semua kasir</option>
                        @foreach ($cashiers as $cashier)
                            <option value="{{ $cashier->id }}" @selected((string) $filters['cashier_id'] === (string) $cashier->id)>{{ $cashier->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="payment_method">Metode</label>
                <select id="payment_method" name="payment_method" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected($filters['payment_method'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-funnel me-1"></i>Terapkan Filter
                </button>
                <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>

    <div class="card ui-table-card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Invoice</th>
                        <th>Tanggal &amp; Jam</th>
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
                            <td class="text-end fw-semibold">{{ $formatRupiah($transaction->total) }}</td>
                            <td>
                                <span class="badge {{ $transaction->status_badge_class }}">{{ $transaction->status_label }}</span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                    <a href="{{ route('kasir.show', $transaction) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Lihat Detail">
                                        <i class="bi bi-eye me-1"></i>Detail
                                    </a>
                                    <a href="{{ route('kasir.print', $transaction) }}" target="_blank" class="btn btn-sm btn-outline-dark" data-bs-toggle="tooltip" title="Cetak Ulang Struk">
                                        <i class="bi bi-printer me-1"></i>Cetak
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="ui-empty-state">
                                    <i class="bi bi-receipt-cutoff"></i>
                                    <div class="fw-semibold">Tidak ada transaksi</div>
                                    <div class="text-muted small">Coba ubah filter pencarian untuk menemukan data yang sesuai.</div>
                                </div>
                            </td>
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
