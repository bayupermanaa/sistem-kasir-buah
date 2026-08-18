<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Detail Stock Opname',
            'subtitle' => 'Tinjau stok sistem, stok fisik, dan selisih yang tersimpan.',
            'icon' => 'bi-clipboard-data',
        ])
    </x-slot>

    @php
        $formatQuantity = fn ($value) => number_format((float) $value, 3, ',', '.');
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Tanggal</span>
                        <span class="ui-kpi-icon"><i class="bi bi-calendar3"></i></span>
                    </div>
                    <div class="ui-summary-value fs-5">{{ $stockOpname->opname_date?->translatedFormat('d M Y') }}</div>
                    <div class="ui-summary-note">Sesi opname tercatat</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Outlet</span>
                        <span class="ui-kpi-icon"><i class="bi bi-shop"></i></span>
                    </div>
                    <div class="ui-summary-value fs-5">{{ $stockOpname->outlet?->name }}</div>
                    <div class="ui-summary-note">Toko utama</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Dibuat Oleh</span>
                        <span class="ui-kpi-icon"><i class="bi bi-person-badge"></i></span>
                    </div>
                    <div class="ui-summary-value fs-5">{{ $stockOpname->creator?->name ?? '-' }}</div>
                    <div class="ui-summary-note">Operator opname</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Jumlah Produk</span>
                        <span class="ui-kpi-icon"><i class="bi bi-boxes"></i></span>
                    </div>
                    <div class="ui-summary-value fs-5">{{ number_format($stockOpname->items->count(), 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Baris opname tersimpan</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ui-surface ui-banner p-4 mb-3">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="fw-semibold mb-1">Catatan</div>
                <div class="text-muted">{{ $stockOpname->notes ?: 'Tidak ada catatan.' }}</div>
            </div>
            <div class="text-lg-end">
                <span class="badge bg-success fs-6">{{ $stockOpname->status_label }}</span>
            </div>
        </div>
    </div>

    <div class="card ui-table-card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Stok Sistem</th>
                        <th>Stok Fisik</th>
                        <th>Selisih</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stockOpname->items as $item)
                        @php
                            $difference = (float) $item->difference;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $item->product?->name }}</div>
                                <div class="text-muted small">{{ $item->product?->code }} &middot; {{ $item->product?->unit?->name }}</div>
                            </td>
                            <td>{{ $formatQuantity($item->system_quantity) }}</td>
                            <td>{{ $formatQuantity($item->physical_quantity) }}</td>
                            <td>
                                @if ($difference > 0)
                                    <span class="badge bg-success">+{{ $formatQuantity($difference) }}</span>
                                @elseif ($difference < 0)
                                    <span class="badge bg-danger">{{ $formatQuantity($difference) }}</span>
                                @else
                                    <span class="badge bg-secondary">Sesuai</span>
                                @endif
                            </td>
                            <td>{{ $item->notes ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <a href="{{ route('stock.opnames.index') }}" class="btn btn-outline-secondary ui-action-icon-btn">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
        </div>
    </div>
</x-app-layout>
