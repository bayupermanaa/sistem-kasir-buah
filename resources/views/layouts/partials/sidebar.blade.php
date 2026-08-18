@php
    $user = auth()->user();
    $role = $user?->role;
    $mobile = $mobile ?? false;

    $ownerMenus = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bi-speedometer2', 'placeholder' => false],
        ['label' => 'Kasir', 'route' => 'kasir', 'icon' => 'bi-cash-coin', 'placeholder' => false],
        ['label' => 'Barang & Stok', 'route' => 'products.index', 'icon' => 'bi-box-seam', 'placeholder' => false],
        ['label' => 'Riwayat Stok', 'route' => 'stock.histories.index', 'icon' => 'bi-clock-history', 'placeholder' => false],
        ['label' => 'Riwayat Transaksi', 'route' => 'transactions.index', 'icon' => 'bi-receipt-cutoff', 'placeholder' => false],
        ['label' => 'Pengeluaran', 'route' => 'expenses.index', 'icon' => 'bi-wallet2', 'placeholder' => false],
        ['label' => 'Stock Opname', 'route' => 'stock.opnames.index', 'icon' => 'bi-clipboard-data', 'placeholder' => false],
        ['label' => 'Laporan Penjualan', 'route' => 'reports.sales', 'icon' => 'bi-graph-up-arrow', 'placeholder' => false],
        ['label' => 'Pengaturan', 'route' => 'pengaturan', 'icon' => 'bi-gear', 'placeholder' => false],
    ];

    $cashierMenus = [
        ['label' => 'Kasir', 'route' => 'kasir', 'icon' => 'bi-cash-coin', 'placeholder' => false],
        ['label' => 'Barang & Stok', 'route' => 'products.index', 'icon' => 'bi-box-seam', 'placeholder' => false],
        ['label' => 'Riwayat Stok', 'route' => 'stock.histories.index', 'icon' => 'bi-clock-history', 'placeholder' => false],
        ['label' => 'Riwayat Transaksi', 'route' => 'transactions.index', 'icon' => 'bi-receipt-cutoff', 'placeholder' => false],
        ['label' => 'Pengeluaran', 'route' => 'expenses.index', 'icon' => 'bi-wallet2', 'placeholder' => false],
        ['label' => 'Laporan Penjualan', 'route' => 'reports.sales', 'icon' => 'bi-graph-up-arrow', 'placeholder' => false],
    ];

    $menus = $role === 'owner' ? $ownerMenus : $cashierMenus;
@endphp

<aside class="ui-sidebar text-white h-100 {{ $mobile ? 'p-3' : 'p-3 border-end border-dark-subtle' }} d-flex flex-column">
    <div class="ui-sidebar-brand d-flex align-items-center gap-3 mb-4">
        <div class="ui-sidebar-brand-mark">
            <i class="bi bi-shop fs-5"></i>
        </div>
        <div class="lh-sm">
            <div class="fw-semibold">Toko Buah Teras Fatimah</div>
            <small class="text-white-50">Sistem Kasir v1.0</small>
        </div>
    </div>

    <div class="text-uppercase small fw-semibold text-white-50 mb-2">Menu</div>

    <ul class="nav nav-pills flex-column gap-2">
        @foreach ($menus as $menu)
            @php
                $active = $menu['route'] && (
                    ($menu['route'] === 'kasir' && request()->routeIs('kasir*')) ||
                    ($menu['route'] === 'products.index' && (
                        request()->routeIs('products.*')
                        || request()->routeIs('stocks.*')
                        || request()->routeIs('categories.*')
                        || request()->routeIs('units.*')
                    )) ||
                    ($menu['route'] === 'transactions.index' && request()->routeIs('transactions.*', 'kasir.show', 'kasir.print')) ||
                    ($menu['route'] === 'expenses.index' && request()->routeIs('expenses.*')) ||
                    ($menu['route'] === 'stock.opnames.index' && request()->routeIs('stock.opnames.*')) ||
                    ($menu['route'] === 'reports.sales' && request()->routeIs('reports.*')) ||
                    request()->routeIs($menu['route'])
                );
            @endphp

            <li class="nav-item">
                @if ($menu['placeholder'])
                    <span class="nav-link ui-sidebar-link placeholder d-flex align-items-center gap-2 px-3 py-2">
                        <i class="bi {{ $menu['icon'] }}"></i>
                        <span>{{ $menu['label'] }}</span>
                    </span>
                @else
                    <a href="{{ route($menu['route']) }}"
                       class="nav-link ui-sidebar-link d-flex align-items-center gap-2 px-3 py-2 {{ $active ? 'active' : '' }}">
                        <i class="bi {{ $menu['icon'] }}"></i>
                        <span>{{ $menu['label'] }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="ui-sidebar-footer mt-4">
        <div class="fw-semibold text-white">Sistem Kasir</div>
        <div class="d-flex align-items-center justify-content-between gap-2">
            <span>Toko Buah Teras Fatimah</span>
            <span class="badge text-bg-light text-dark">v1.0</span>
        </div>
    </div>
</aside>
