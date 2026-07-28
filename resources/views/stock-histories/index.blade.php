<x-app-layout>
    <style>
        .bg-purple {
            background-color: #6f42c1 !important;
        }
    </style>

    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Riwayat Stok',
            'subtitle' => 'Catatan setiap perubahan stok pada toko utama.',
            'icon' => 'bi-clock-history',
        ])
    </x-slot>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('stock.histories.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="search">Cari produk</label>
                    <input type="text" name="search" id="search" class="form-control" value="{{ request('search') }}" placeholder="Kode / nama produk">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="type">Jenis</label>
                    <select name="type" id="type" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="date_from">Dari</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="date_to">Sampai</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Terapkan</button>
                    <a href="{{ route('stock.histories.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
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
                            <td>{{ $history->created_at->format('d/m/Y H:i') }}</td>
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
                            <td class="text-end">{{ number_format((float) $history->quantity_before, 3, ',', '.') }}</td>
                            <td class="text-end">
                                <span class="{{ $history->quantity_change_class }}">
                                    {{ $history->formatted_quantity_change }}
                                </span>
                            </td>
                            <td class="text-end">{{ number_format((float) $history->quantity_after, 3, ',', '.') }}</td>
                            <td>{{ $history->reason ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Tidak ada riwayat stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $histories->links() }}
        </div>
    </div>
</x-app-layout>
