<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Edit Produk',
            'subtitle' => 'Perbarui informasi produk yang sudah ada.',
            'icon' => 'bi-pencil-square',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @include('products.partials.form', [
                'action' => route('products.update', $product),
                'method' => 'PUT',
                'buttonLabel' => 'Perbarui Produk',
                'product' => $product,
                'categories' => $categories,
                'units' => $units,
            ])
        </div>
    </div>
</x-app-layout>
