@php
    $user = auth()->user();
    $role = $user?->role;
    $mobile = $mobile ?? false;

    $ownerMenus = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bi-speedometer2', 'placeholder' => false],
        ['label' => 'Kasir', 'route' => 'kasir', 'icon' => 'bi-cash-coin', 'placeholder' => false],
        ['label' => 'Barang & Stok', 'route' => 'products.index', 'icon' => 'bi-box-seam', 'placeholder' => false],
        ['label' => 'Riwayat Stok', 'route' => 'stock.histories.index', 'icon' => 'bi-clock-history', 'placeholder' => false],
        ['label' => 'Riwayat Transaksi', 'route' => null, 'icon' => 'bi-receipt', 'placeholder' => true],
        ['label' => 'Pengeluaran', 'route' => null, 'icon' => 'bi-wallet2', 'placeholder' => true],
        ['label' => 'Stock Opname', 'route' => null, 'icon' => 'bi-clipboard-data', 'placeholder' => true],
        ['label' => 'Laporan', 'route' => null, 'icon' => 'bi-graph-up', 'placeholder' => true],
        ['label' => 'Pengaturan', 'route' => 'pengaturan', 'icon' => 'bi-gear', 'placeholder' => false],
    ];

    $cashierMenus = [
        ['label' => 'Kasir', 'route' => 'kasir', 'icon' => 'bi-cash-coin', 'placeholder' => false],
        ['label' => 'Barang & Stok', 'route' => 'products.index', 'icon' => 'bi-box-seam', 'placeholder' => false],
        ['label' => 'Riwayat Stok', 'route' => 'stock.histories.index', 'icon' => 'bi-clock-history', 'placeholder' => false],
        ['label' => 'Riwayat Transaksi', 'route' => null, 'icon' => 'bi-receipt', 'placeholder' => true],
        ['label' => 'Pengeluaran', 'route' => null, 'icon' => 'bi-wallet2', 'placeholder' => true],
        ['label' => 'Stock Opname', 'route' => null, 'icon' => 'bi-clipboard-data', 'placeholder' => true],
    ];

    $menus = $role === 'owner' ? $ownerMenus : $cashierMenus;
@endphp

<aside class="bg-dark text-white h-100 {{ $mobile ? 'p-3' : 'p-3 border-end border-dark-subtle' }}">
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;">
            <i class="bi bi-shop"></i>
        </div>
        <div class="lh-sm">
            <div class="fw-semibold">Sistem Kasir</div>
            <small class="text-white-50">Toko Buah Teras Fatimah</small>
        </div>
    </div>

    <ul class="nav nav-pills flex-column gap-1">
        @foreach ($menus as $menu)
            @php
                $active = $menu['route'] && (
                    request()->routeIs($menu['route']) ||
                    ($menu['route'] === 'products.index' && (
                        request()->routeIs('products.*')
                        || request()->routeIs('stocks.*')
                        || request()->routeIs('categories.*')
                        || request()->routeIs('units.*')
                    ))
                );
            @endphp

            <li class="nav-item">
                @if ($menu['placeholder'])
                    <span class="nav-link d-flex align-items-center gap-2 text-white-50 px-3 py-2">
                        <i class="bi {{ $menu['icon'] }}"></i>
                        <span>{{ $menu['label'] }}</span>
                    </span>
                @else
                    <a href="{{ route($menu['route']) }}"
                       class="nav-link d-flex align-items-center gap-2 px-3 py-2 {{ $active ? 'active bg-primary text-white' : 'text-white-50' }}">
                        <i class="bi {{ $menu['icon'] }}"></i>
                        <span>{{ $menu['label'] }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</aside>
