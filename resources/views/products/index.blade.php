<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Barang & Stok',
            'subtitle' => 'Stok toko utama dengan status minimum dan aksi penyesuaian.',
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

    <div class="ui-surface ui-filter-card p-3 p-lg-4 mb-3">
        <form method="GET" action="{{ route('products.index') }}" class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label fw-semibold" for="search">Cari produk</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Kode atau nama barang">
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label fw-semibold" for="category_id">Kategori</label>
                <select name="category_id" id="category_id" class="form-select form-select-lg">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label fw-semibold" for="status">Status Stok</label>
                <select name="status" id="status" class="form-select form-select-lg">
                    <option value="">Semua Status</option>
                    @foreach ($statusOptions as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected($selectedStatus === $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button class="btn btn-primary btn-lg flex-grow-1" type="submit">
                    <i class="bi bi-search me-1"></i>Cari
                </button>
                <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @if (auth()->user()->role === 'owner')
            <a href="{{ route('products.create') }}" class="btn btn-primary ui-action-icon-btn">
                <i class="bi bi-plus-circle"></i>
                Tambah Produk
            </a>
            <a href="{{ route('categories.index') }}" class="btn btn-outline-primary ui-action-icon-btn">
                <i class="bi bi-tags"></i>
                Kategori
            </a>
            <a href="{{ route('units.index') }}" class="btn btn-outline-primary ui-action-icon-btn">
                <i class="bi bi-rulers"></i>
                Satuan
            </a>
        @endif
        <a href="{{ route('stock.histories.index') }}" class="btn btn-outline-secondary ui-action-icon-btn">
            <i class="bi bi-clock-history"></i>
            Riwayat Stok
        </a>
    </div>

    <div class="card ui-section-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Produk</th>
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
                        @php
                            $product = $stock->product;
                        @endphp
                        @if (! $product)
                            @continue
                        @endif
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if ($product?->image_url)
                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="ui-product-thumb">
                                    @else
                                        <div class="ui-product-thumb-placeholder">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-semibold">{{ $product?->name }}</div>
                                        <div class="text-muted small">{{ $product?->code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $product?->category?->name }}</td>
                            <td>{{ $product?->unit?->name }} ({{ $product?->unit?->symbol }})</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $stock->quantity, 3, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $stock->minimum_stock, 3, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $stock->quantity <= 0 ? 'ui-badge-stock-empty' : ($stock->isLow() ? 'ui-badge-stock-low' : 'ui-badge-stock-safe') }}">
                                    {{ $stock->statusLabel() }}
                                </span>
                                @if ($stock->isLow())
                                    <span class="badge ui-badge-stock-low ms-1">Stok menipis</span>
                                @elseif ($stock->isOut())
                                    <span class="badge ui-badge-stock-empty ms-1">Stok habis</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="ui-action-stack">
                                    @if (auth()->user()->role === 'owner')
                                        <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary ui-action-icon-btn ui-table-action" data-bs-toggle="tooltip" title="Edit Produk" aria-label="Edit Produk">
                                            <i class="bi bi-pencil-square"></i>
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('products.destroy', $product) }}" class="delete-form d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger ui-action-icon-btn ui-table-action" data-bs-toggle="tooltip" title="Hapus Produk" aria-label="Hapus Produk">
                                                <i class="bi bi-trash"></i>
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('stocks.adjust.create', $stock->product) }}" class="btn btn-sm btn-primary ui-action-icon-btn ui-table-action">
                                        <i class="bi bi-sliders"></i>
                                        Penyesuaian
                                    </a>
                                    @if (auth()->user()->role === 'owner')
                                        <a href="{{ route('stocks.minimum.edit', $stock) }}" class="btn btn-sm btn-outline-secondary ui-action-icon-btn ui-table-action">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Minimum
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="ui-empty-state ui-empty-state-panel my-2">
                                    <i class="bi bi-box-seam"></i>
                                    <div class="fw-semibold">Belum ada data stok</div>
                                    <div class="text-muted small">Produk yang aktif akan muncul di sini bersama status stoknya.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            {{ $stocks->links() }}
        </div>
    </div>
</x-app-layout>
