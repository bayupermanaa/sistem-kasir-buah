<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Tambah Aset',
            'subtitle' => 'Catat pembelian aset tetap toko secara terstruktur.',
            'icon' => 'bi-safe2',
        ])
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">
            <div class="ui-surface ui-section-card p-3 p-lg-4">
                @include('assets.partials.form', [
                    'action' => route('assets.store'),
                    'method' => 'POST',
                    'buttonLabel' => 'Simpan Aset',
                    'asset' => null,
                    'categories' => $categories,
                    'statusOptions' => $statusOptions,
                    'paymentMethods' => $paymentMethods,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
