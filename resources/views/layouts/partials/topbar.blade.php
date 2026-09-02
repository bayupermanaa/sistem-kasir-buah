@php
    $user = auth()->user();

    $titles = [
        'dashboard' => 'Dashboard Pemilik',
        'products.index' => 'Barang & Stok',
        'stocks.adjust.create' => 'Penyesuaian Stok',
        'stocks.minimum.edit' => 'Minimum Stok',
        'stock.histories.index' => 'Riwayat Stok',
        'transactions.index' => 'Riwayat Transaksi',
        'expenses.index' => 'Pengeluaran',
        'reports.index' => 'Laporan',
        'reports.sales' => 'Laporan Penjualan',
        'reports.expenses' => 'Laporan Pengeluaran',
        'reports.profit-loss' => 'Laporan Laba Rugi',
        'reports.cash-flow' => 'Laporan Arus Kas',
        'reports.balance-sheet' => 'Neraca',
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

<nav class="navbar navbar-expand-lg ui-topbar px-3 px-lg-4 border-bottom sticky-top">
    <div class="container-fluid px-0 gap-3 align-items-center">
        <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
            <i class="bi bi-list"></i>
        </button>

        <div class="me-auto d-flex flex-column gap-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge text-bg-light text-dark border">Toko Buah Teras Fatimah</span>
                <span class="badge ui-role-badge-{{ $user?->role === 'owner' ? 'owner' : 'cashier' }}">
                    {{ ucfirst($user?->role ?? '-') }}
                </span>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold text-dark">{{ $title }}</span>
                <span class="text-muted">•</span>
                <span class="text-muted">{{ $user?->name }}</span>
            </div>
        </div>

        <div class="ui-topbar-meta">
            <div class="ui-topbar-chip ui-topbar-clock d-none d-md-flex flex-column align-items-start">
                <span class="small text-muted">Hari ini</span>
                <span class="fw-semibold" data-topbar-date>{{ now()->format('d/m/Y') }}</span>
                <span class="text-accent fw-semibold" data-topbar-time>--:--:--</span>
            </div>

            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="badge text-bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 2.2rem; height: 2.2rem; line-height: 1.4;">
                        {{ strtoupper(substr($user?->name ?? 'U', 0, 1)) }}
                    </span>
                    <span class="d-none d-md-inline text-start">
                        <span class="d-block fw-semibold">{{ $user?->name }}</span>
                        <small class="text-muted text-capitalize">{{ $user?->role }}</small>
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <div class="dropdown-item-text small text-muted">
                            <div class="fw-semibold text-dark">{{ $user?->name }}</div>
                            <div class="text-capitalize">Role: {{ $user?->role }}</div>
                            @if ($user?->outlet)
                                <div>Toko: {{ $user->outlet->name }}</div>
                            @endif
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
    </div>
</nav>
