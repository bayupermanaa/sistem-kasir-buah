<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Edit Satuan',
            'subtitle' => 'Perbarui data satuan yang dipilih.',
            'icon' => 'bi-pencil-square',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="card ui-section-card">
        <div class="card-body p-4 p-lg-5">
            @include('units.partials.form', [
                'action' => route('units.update', $unit),
                'method' => 'PUT',
                'buttonLabel' => 'Perbarui Satuan',
                'unit' => $unit,
            ])
        </div>
    </div>
</x-app-layout>
