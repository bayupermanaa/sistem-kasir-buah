<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Halaman Kasir',
            'subtitle' => 'Area kerja kasir untuk transaksi harian.',
            'icon' => 'bi-cash-coin',
        ])
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-success-subtle text-success rounded-3 d-inline-flex align-items-center justify-content-center" style="width: 3rem; height: 3rem;">
                    <i class="bi bi-cash-coin fs-4"></i>
                </div>
                <div>
                    <div class="fw-semibold">Halaman kasir siap dipakai</div>
                    <div class="text-muted">Fitur transaksi akan ditambahkan pada tahap berikutnya.</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
