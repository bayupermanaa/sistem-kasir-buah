<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Riwayat Stok',
            'subtitle' => 'Catatan setiap perubahan stok pada toko utama.',
            'icon' => 'bi-clock-history',
        ])
    </x-slot>

    <div class="ui-surface ui-filter-card p-3 p-lg-4 mb-3">
        <form method="GET" action="{{ route('stock.histories.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4 col-lg-3">
                <label class="form-label fw-semibold" for="search">Cari produk</label>
                <input type="text" name="search" id="search" class="form-control" value="{{ request('search') }}" placeholder="Kode / nama produk">
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="type">Jenis</label>
                <select name="type" id="type" class="form-select">
                    <option value="">Semua Jenis</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ $typeOptions[$type]['label'] ?? str_replace('_', ' ', ucfirst($type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="date_from">Dari</label>
                <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="date_to">Sampai</label>
                <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <a href="{{ route('stock.histories.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>

    <div class="card ui-section-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Produk</th>
                        <th>Pengguna</th>
                        <th>Jenis</th>
                        <th class="text-end">Stok Sebelum</th>
                        <th class="text-end">Perubahan</th>
                        <th class="text-end">Stok Sesudah</th>
                        <th>Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($histories as $history)
                        <tr>
                            <td class="text-nowrap">{{ $history->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $history->product?->name }}</div>
                                <div class="text-muted small">{{ $history->product?->code }}</div>
                            </td>
                            <td>{{ $history->user?->name }}</td>
                            <td>
                                <span class="badge {{ $history->type_badge_class }}">
                                    <i class="bi {{ $history->type_icon }} me-1"></i>
                                    {{ $history->type_label }}
                                </span>
                            </td>
                            <td class="text-end text-muted">{{ number_format((float) $history->quantity_before, 3, ',', '.') }}</td>
                            <td class="text-end">
                                <span class="fw-semibold {{ $history->quantity_change_class }}">
                                    {{ $history->formatted_quantity_change }}
                                </span>
                            </td>
                            <td class="text-end text-muted">{{ number_format((float) $history->quantity_after, 3, ',', '.') }}</td>
                            <td>{{ $history->reason ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="ui-empty-state ui-empty-state-panel my-2">
                                    <i class="bi bi-clock-history"></i>
                                    <div class="fw-semibold">Belum ada riwayat stok</div>
                                    <div class="text-muted small">Perubahan stok akan muncul di sini setelah ada aktivitas penyesuaian.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            {{ $histories->links() }}
        </div>
    </div>
</x-app-layout>
