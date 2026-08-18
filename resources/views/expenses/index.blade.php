<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Pengeluaran',
            'subtitle' => 'Catat dan pantau biaya operasional toko secara rapi.',
            'icon' => 'bi-wallet2',
        ])
    </x-slot>

    @php
        $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Jumlah Pengeluaran</span>
                        <span class="ui-kpi-icon"><i class="bi bi-receipt"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((int) $summary['count'], 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Data pada filter aktif</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Total Nominal</span>
                        <span class="ui-kpi-icon"><i class="bi bi-cash-coin"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ $formatRupiah($summary['amount']) }}</div>
                    <div class="ui-summary-note">Akumulasi pengeluaran tersaring</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Outlet</span>
                        <span class="ui-kpi-icon"><i class="bi bi-shop"></i></span>
                    </div>
                    <div class="ui-summary-value fs-5">{{ $outlet->name }}</div>
                    <div class="ui-summary-note">Semua pengeluaran tersimpan pada toko utama</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ui-surface ui-filter-card p-3 p-lg-4 mb-3">
        <form method="GET" action="{{ route('expenses.index') }}" class="row g-3 align-items-end" data-submit-loading>
            <div class="col-md-6 col-lg-3">
                <label class="form-label fw-semibold" for="search">Pencarian</label>
                <input type="text" id="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Keterangan atau kategori">
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label fw-semibold" for="category">Kategori</label>
                <select id="category" name="category" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="date_from">Dari</label>
                <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="date_to">Sampai</label>
                <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-funnel me-1"></i>Terapkan Filter
                </button>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div class="text-muted small">
            Pengeluaran dicatat otomatis untuk outlet utama dan pengguna yang login.
        </div>
        <a href="{{ route('expenses.create') }}" class="btn btn-primary ui-action-icon-btn">
            <i class="bi bi-plus-circle"></i>
            Tambah Pengeluaran
        </a>
    </div>

    <div class="card ui-table-card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th class="text-end">Nominal</th>
                        <th>Dicatat Oleh</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td class="fw-semibold">{{ $expense->expense_date?->translatedFormat('d M Y') }}</td>
                            <td>{{ $expense->category }}</td>
                            <td>
                                <div class="fw-semibold">{{ $expense->description }}</div>
                                @if ($expense->notes)
                                    <div class="text-muted small">{{ $expense->notes }}</div>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">{{ $formatRupiah($expense->amount) }}</td>
                            <td>{{ $expense->creator?->name ?? '-' }}</td>
                            <td class="text-end">
                                @if (auth()->user()->role === 'owner')
                                    <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                        <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-sm btn-outline-primary ui-action-icon-btn" data-bs-toggle="tooltip" title="Edit Pengeluaran" aria-label="Edit Pengeluaran">
                                            <i class="bi bi-pencil-square"></i>
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="delete-form d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger ui-action-icon-btn" data-bs-toggle="tooltip" title="Hapus Pengeluaran" aria-label="Hapus Pengeluaran">
                                                <i class="bi bi-trash"></i>
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted small">Pencatatan operasional</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="ui-empty-state ui-empty-state-panel my-2">
                                    <i class="bi bi-wallet2"></i>
                                    <div class="fw-semibold">Belum ada pengeluaran</div>
                                    <div class="text-muted small">Tambahkan pengeluaran pertama agar catatan operasional toko tersimpan.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            {{ $expenses->links() }}
        </div>
    </div>
</x-app-layout>
