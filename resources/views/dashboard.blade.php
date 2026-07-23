<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Dashboard Pemilik',
            'subtitle' => 'Ringkasan awal untuk pemilik toko.',
            'icon' => 'bi-speedometer2',
        ])
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-primary-subtle text-primary rounded-3 d-inline-flex align-items-center justify-content-center" style="width: 3rem; height: 3rem;">
                        <i class="bi bi-speedometer2 fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Status</div>
                        <div class="fw-semibold">Dashboard siap digunakan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
