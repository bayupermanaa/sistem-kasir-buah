<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Stock Opname',
            'subtitle' => 'Cocokkan stok sistem dengan stok fisik dan simpan selisihnya secara aman.',
            'icon' => 'bi-clipboard-check',
        ])
    </x-slot>

    @php
        $formatQuantity = fn ($value) => number_format((float) $value, 3, ',', '.');
        $formatDate = fn ($value) => $value?->translatedFormat('d M Y');
    @endphp

    <div class="ui-banner ui-surface p-4 mb-3">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 ui-badge-soft-primary px-3 py-2 rounded-pill fw-semibold mb-3">
                    <i class="bi bi-shop"></i>
                    {{ $outlet->name }}
                </div>
                <h2 class="fw-bold mb-2">Daftar Stock Opname</h2>
                <p class="text-muted mb-0">
                    Semua opname menggunakan outlet utama, sehingga hasil selisih langsung sinkron ke stok dan riwayat stok.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="{{ route('stock.opnames.create') }}" class="btn btn-primary ui-action-icon-btn">
                    <i class="bi bi-plus-circle"></i>
                    Mulai Stock Opname
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Total Sesi</span>
                        <span class="ui-kpi-icon"><i class="bi bi-clipboard-data"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((int) $summary['session_count'], 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Riwayat opname tersimpan</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Produk Terhitung</span>
                        <span class="ui-kpi-icon"><i class="bi bi-boxes"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((int) $summary['product_count'], 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Jumlah baris opname pada halaman ini</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Outlet</span>
                        <span class="ui-kpi-icon"><i class="bi bi-shop"></i></span>
                    </div>
                    <div class="ui-summary-value fs-5">{{ $outlet->name }}</div>
                    <div class="ui-summary-note">Stock Opname hanya untuk toko utama</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card ui-table-card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jumlah Produk</th>
                        <th>Total Selisih</th>
                        <th>Dibuat Oleh</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockOpnames as $stockOpname)
                        <tr>
                            <td class="fw-semibold">{{ $formatDate($stockOpname->opname_date) }}</td>
                            <td>{{ number_format((int) $stockOpname->items_count, 0, ',', '.') }}</td>
                            <td class="fw-semibold">{{ $formatQuantity($stockOpname->total_difference ?? 0) }}</td>
                            <td>{{ $stockOpname->creator?->name ?? '-' }}</td>
                            <td>
                                <span class="badge bg-success">{{ $stockOpname->status_label }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('stock.opnames.show', $stockOpname) }}" class="btn btn-sm btn-outline-primary ui-action-icon-btn" data-bs-toggle="tooltip" title="Lihat Detail Stock Opname" aria-label="Lihat Detail Stock Opname">
                                    <i class="bi bi-eye"></i>
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="ui-empty-state ui-empty-state-panel my-2">
                                    <i class="bi bi-clipboard-check"></i>
                                    <div class="fw-semibold">Belum ada stock opname</div>
                                    <div class="text-muted small">Mulai sesi opname pertama untuk mencocokkan stok sistem dengan stok fisik.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            {{ $stockOpnames->links() }}
        </div>
    </div>
</x-app-layout>
