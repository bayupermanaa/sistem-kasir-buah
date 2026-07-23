<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Edit Kategori',
            'subtitle' => 'Perbarui data kategori yang dipilih.',
            'icon' => 'bi-pencil-square',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @include('categories.partials.form', [
                'action' => route('categories.update', $category),
                'method' => 'PUT',
                'buttonLabel' => 'Perbarui Kategori',
                'category' => $category,
            ])
        </div>
    </div>
</x-app-layout>
