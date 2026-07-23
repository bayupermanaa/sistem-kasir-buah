<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Barang & Stok',
            'subtitle' => 'Stok per outlet dengan status minimum dan aksi penyesuaian.',
            'icon' => 'bi-box-seam',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    @if ($lowStockCount > 0)
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Ada {{ $lowStockCount }} stok yang sudah menyentuh atau melewati minimum stok.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('products.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="search">Cari produk</label>
                    <input type="text" id="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Kode atau nama barang">
                </div>
                @if (auth()->user()->role === 'owner')
                    <div class="col-md-4">
                        <label class="form-label" for="outlet_id">Filter outlet</label>
                        <select id="outlet_id" name="outlet_id" class="form-select">
                            <option value="">Semua outlet</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" @selected((string) request('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="{{ auth()->user()->role === 'owner' ? 'col-md-3' : 'col-md-7' }} d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Cari</button>
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @if (auth()->user()->role === 'owner')
            <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Produk</a>
            <a href="{{ route('categories.index') }}" class="btn btn-outline-primary">Kategori</a>
            <a href="{{ route('units.index') }}" class="btn btn-outline-primary">Satuan</a>
        @endif
        <a href="{{ route('stock.histories.index') }}" class="btn btn-outline-secondary">Riwayat Stok</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Outlet</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th class="text-end">Stok</th>
                        <th class="text-end">Minimum</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stock)
                        <tr>
                            <td>{{ $stock->outlet?->name }}</td>
                            <td class="fw-semibold">{{ $stock->product?->code }}</td>
                            <td>{{ $stock->product?->name }}</td>
                            <td>{{ $stock->product?->category?->name }}</td>
                            <td>{{ $stock->product?->unit?->name }} ({{ $stock->product?->unit?->symbol }})</td>
                            <td class="text-end">{{ number_format((float) $stock->quantity, 3, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $stock->minimum_stock, 3, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $stock->statusBadgeClass() }}">{{ $stock->statusLabel() }}</span>
                                @if ($stock->isLow())
                                    <span class="badge text-bg-warning ms-1">Perlu perhatian</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('stocks.adjust.create', $stock->product) }}?outlet_id={{ $stock->outlet_id }}" class="btn btn-sm btn-outline-primary">Penyesuaian</a>
                                    @if (auth()->user()->role === 'owner')
                                        <a href="{{ route('stocks.minimum.edit', $stock) }}" class="btn btn-sm btn-outline-secondary">Minimum</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Tidak ada data stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $stocks->links() }}
        </div>
    </div>
</x-app-layout>
