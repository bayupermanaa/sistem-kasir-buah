<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Tambah Pengeluaran',
            'subtitle' => 'Catat biaya operasional toko dengan aman.',
            'icon' => 'bi-wallet2',
        ])
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-8">
            <div class="ui-surface ui-section-card p-3 p-lg-4">
                @include('expenses.partials.form', [
                    'action' => route('expenses.store'),
                    'method' => 'POST',
                    'buttonLabel' => 'Simpan Pengeluaran',
                    'expense' => null,
                    'categories' => $categories,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
