<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Halaman Pengaturan',
            'subtitle' => 'Kelola pengaturan dasar sistem dan akses pengguna.',
            'icon' => 'bi-gear',
        ])
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-warning-subtle text-warning rounded-3 d-inline-flex align-items-center justify-content-center" style="width: 3rem; height: 3rem;">
                    <i class="bi bi-gear fs-4"></i>
                </div>
                <div>
                    <div class="fw-semibold">Pengaturan aplikasi</div>
                    <div class="text-muted">Menu ini akan dipakai untuk pengaturan pengguna dan outlet.</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
