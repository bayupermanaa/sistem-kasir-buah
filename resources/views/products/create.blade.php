<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Tambah Produk',
            'subtitle' => 'Buat data produk baru untuk katalog barang.',
            'icon' => 'bi-plus-circle',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @include('products.partials.form', [
                'action' => route('products.store'),
                'method' => 'POST',
                'buttonLabel' => 'Simpan Produk',
                'categories' => $categories,
                'units' => $units,
            ])
        </div>
    </div>
</x-app-layout>
