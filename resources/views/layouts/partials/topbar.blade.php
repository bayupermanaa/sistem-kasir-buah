@php
    $user = auth()->user();

    $titles = [
        'dashboard' => 'Dashboard Pemilik',
        'products.index' => 'Barang & Stok',
        'stocks.adjust.create' => 'Penyesuaian Stok',
        'stocks.minimum.edit' => 'Minimum Stok',
        'stock.histories.index' => 'Riwayat Stok',
        'transactions.index' => 'Riwayat Transaksi',
        'reports.sales' => 'Laporan Penjualan',
        'products.create' => 'Tambah Produk',
        'products.edit' => 'Edit Produk',
        'categories.index' => 'Kategori',
        'categories.create' => 'Tambah Kategori',
        'categories.edit' => 'Edit Kategori',
        'units.index' => 'Satuan',
        'units.create' => 'Tambah Satuan',
        'units.edit' => 'Edit Satuan',
        'kasir' => 'Halaman Kasir',
        'kasir.show' => 'Transaksi Selesai',
        'pengaturan' => 'Halaman Pengaturan',
        'profile.edit' => 'Profil Akun',
    ];

    $title = collect($titles)->first(fn ($value, $routeName) => request()->routeIs($routeName)) ?? 'Sistem Kasir';
@endphp

<nav class="navbar navbar-expand-lg bg-white border-bottom px-3 px-lg-4 shadow-sm sticky-top">
    <div class="container-fluid px-0">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
            <i class="bi bi-list"></i>
        </button>

        <div class="d-flex flex-column">
            <span class="fw-semibold text-dark">{{ $title }}</span>
            <small class="text-muted text-capitalize">
                {{ $user?->name }} &middot; Role: {{ $user?->role }}
                @if ($user?->outlet)
                    &middot; Toko: {{ $user->outlet->name }}
                @endif
            </small>
        </div>

        <div class="ms-auto dropdown">
            <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="badge text-bg-primary rounded-circle" style="width: 2rem; height: 2rem; line-height: 1.4;">
                    {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                </span>
                <span class="d-none d-md-inline">{{ $user?->name }}</span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <div class="dropdown-item-text small text-muted">
                        <div class="fw-semibold text-dark">{{ $user?->name }}</div>
                        <div class="text-capitalize">Role: {{ $user?->role }}</div>
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profil</a></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item text-danger" type="submit">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>
