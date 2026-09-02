<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Aset',
            'subtitle' => 'Kelola aset toko seperti laptop, kulkas, rak, dan peralatan lainnya.',
            'icon' => 'bi-safe2',
        ])
    </x-slot>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Jumlah Aset</span>
                        <span class="ui-kpi-icon"><i class="bi bi-safe2"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((int) $summary['count'], 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Data pada toko utama</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Total Nilai Aset</span>
                        <span class="ui-kpi-icon"><i class="bi bi-cash-coin"></i></span>
                    </div>
                    <div class="ui-summary-value">Rp {{ number_format((float) $summary['total_cost'], 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Akumulasi total biaya aset</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Aset Aktif</span>
                        <span class="ui-kpi-icon"><i class="bi bi-check2-circle"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((int) $summary['active_count'], 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Status Aktif</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ui-surface ui-filter-card p-3 p-lg-4 mb-3">
        <form method="GET" action="{{ route('assets.index') }}" class="row g-3 align-items-end" data-submit-loading>
            <div class="col-md-6 col-lg-4">
                <label class="form-label fw-semibold" for="search">Pencarian</label>
                <input type="text" id="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Kode, nama, kategori">
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label fw-semibold" for="category">Kategori</label>
                <select id="category" name="category" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $item)
                        <option value="{{ $item }}" @selected($filters['category'] === $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label fw-semibold" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    @foreach ($statusOptions as $item)
                        <option value="{{ $item }}" @selected($filters['status'] === $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <a href="{{ route('assets.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div class="text-muted small">Aset hanya bisa dikelola oleh owner. Cashier tidak memiliki akses.</div>
        <a href="{{ route('assets.create') }}" class="btn btn-primary ui-action-icon-btn">
            <i class="bi bi-plus-circle"></i>
            Tambah Aset
        </a>
    </div>

    <div class="card ui-table-card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Tanggal Beli</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Nilai Aset</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assets as $asset)
                        <tr>
                            <td class="fw-semibold">{{ $asset->asset_code }}</td>
                            <td>
                                <div class="fw-semibold">{{ $asset->name }}</div>
                                <div class="text-muted small">{{ $asset->payment_method_label }}</div>
                            </td>
                            <td>{{ $asset->category }}</td>
                            <td>{{ $asset->formatted_purchase_date }}</td>
                            <td class="text-end">{{ number_format((int) $asset->quantity, 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">{{ $asset->formatted_total_cost }}</td>
                            <td>
                                <span class="badge {{ $asset->status_badge_class }}">{{ $asset->status_label }}</span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                    <a href="{{ route('assets.show', $asset) }}" class="btn btn-sm btn-outline-secondary ui-action-icon-btn" data-bs-toggle="tooltip" title="Detail Aset" aria-label="Detail Aset">
                                        <i class="bi bi-eye"></i>
                                        Detail
                                    </a>
                                    <a href="{{ route('assets.edit', $asset) }}" class="btn btn-sm btn-outline-primary ui-action-icon-btn" data-bs-toggle="tooltip" title="Edit Aset" aria-label="Edit Aset">
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('assets.destroy', $asset) }}" class="delete-form d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger ui-action-icon-btn" data-bs-toggle="tooltip" title="Hapus Aset" aria-label="Hapus Aset">
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="ui-empty-state ui-empty-state-panel my-2">
                                    <i class="bi bi-safe2"></i>
                                    <div class="fw-semibold">Belum ada aset</div>
                                    <div class="text-muted small">Tambahkan aset pertama untuk mulai mencatat investasi toko.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            {{ $assets->links() }}
        </div>
    </div>
</x-app-layout>
