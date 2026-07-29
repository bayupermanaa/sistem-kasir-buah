<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Tambah Kategori',
            'subtitle' => 'Tambahkan kategori baru untuk katalog produk.',
            'icon' => 'bi-plus-circle',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="card ui-section-card">
        <div class="card-body p-4 p-lg-5">
            @include('categories.partials.form', [
                'action' => route('categories.store'),
                'method' => 'POST',
                'buttonLabel' => 'Simpan Kategori',
            ])
        </div>
    </div>
</x-app-layout>
