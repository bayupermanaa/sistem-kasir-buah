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

<div class="ui-surface ui-filter-card p-2 mb-4">
    <ul class="nav nav-pills gap-2">
    @foreach ($items as $item)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                {{ $item['label'] }}
            </a>
        </li>
    @endforeach
    </ul>
</div>
