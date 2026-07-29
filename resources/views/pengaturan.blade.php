<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Halaman Pengaturan',
            'subtitle' => 'Kelola pengaturan dasar sistem dan akses pengguna.',
            'icon' => 'bi-gear',
        ])
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="ui-form-section h-100">
                <div class="ui-form-section-heading">
                    <div class="ui-form-section-icon">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div>
                        <h2>Informasi Toko</h2>
                        <p>Bagian ini disiapkan untuk konfigurasi identitas toko dan data operasional dasar.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="ui-surface p-3 h-100">
                            <div class="text-muted small mb-1">Nama Toko</div>
                            <div class="fw-semibold">Toko Buah Teras Fatimah</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="ui-surface p-3 h-100">
                            <div class="text-muted small mb-1">Status</div>
                            <div class="fw-semibold text-success">Aktif</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="ui-form-section h-100">
                <div class="ui-form-section-heading">
                    <div class="ui-form-section-icon">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div>
                        <h2>Informasi Akun</h2>
                        <p>Ringkasan singkat akun login yang aktif saat ini.</p>
                    </div>
                </div>

                <div class="ui-surface p-3">
                    <div class="text-muted small mb-1">Nama Pengguna</div>
                    <div class="fw-semibold">{{ auth()->user()->name ?? '-' }}</div>
                    <div class="text-muted small mt-3 mb-1">Role</div>
                    <div class="fw-semibold text-capitalize">{{ auth()->user()->role ?? '-' }}</div>
                    @if (auth()->user()?->outlet)
                        <div class="text-muted small mt-3 mb-1">Toko</div>
                        <div class="fw-semibold">{{ auth()->user()->outlet->name }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
