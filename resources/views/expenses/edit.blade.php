<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Edit Pengeluaran',
            'subtitle' => 'Perbarui catatan biaya operasional toko.',
            'icon' => 'bi-wallet2',
        ])
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-8">
            <div class="ui-surface ui-section-card p-3 p-lg-4">
                @include('expenses.partials.form', [
                    'action' => route('expenses.update', $expense),
                    'method' => 'PUT',
                    'buttonLabel' => 'Perbarui Pengeluaran',
                    'expense' => $expense,
                    'categories' => $categories,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
