<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Edit Aset',
            'subtitle' => 'Perbarui data aset tanpa mengubah kode aset.',
            'icon' => 'bi-safe2',
        ])
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">
            <div class="ui-surface ui-section-card p-3 p-lg-4">
                @include('assets.partials.form', [
                    'action' => route('assets.update', $asset),
                    'method' => 'PUT',
                    'buttonLabel' => 'Perbarui Aset',
                    'asset' => $asset,
                    'categories' => $categories,
                    'statusOptions' => $statusOptions,
                    'paymentMethods' => $paymentMethods,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
