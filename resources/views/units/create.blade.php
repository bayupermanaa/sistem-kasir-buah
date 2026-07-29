<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Tambah Satuan',
            'subtitle' => 'Tambahkan satuan baru untuk produk.',
            'icon' => 'bi-plus-circle',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="card ui-section-card">
        <div class="card-body p-4 p-lg-5">
            @include('units.partials.form', [
                'action' => route('units.store'),
                'method' => 'POST',
                'buttonLabel' => 'Simpan Satuan',
            ])
        </div>
    </div>
</x-app-layout>
