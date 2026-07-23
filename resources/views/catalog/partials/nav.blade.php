@php
    $userRole = auth()->user()?->role;
    $items = [
        ['label' => 'Barang & Stok', 'route' => 'products.index'],
    ];

    if ($userRole === 'owner') {
        $items[] = ['label' => 'Kategori', 'route' => 'categories.index'];
        $items[] = ['label' => 'Satuan', 'route' => 'units.index'];
    }
@endphp

<ul class="nav nav-tabs mb-4">
    @foreach ($items as $item)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                {{ $item['label'] }}
            </a>
        </li>
    @endforeach
</ul>
