<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Neraca',
            'subtitle' => 'Posisi aset, kewajiban, dan ekuitas berdasarkan data yang tersedia.',
            'icon' => 'bi-clipboard-data',
        ])
    </x-slot>

    @include('reports.partials.back-link')
    @include('reports.partials.financial-filter')

    @php
        $formatRupiah = fn ($value) => $value === null ? 'Belum tersedia' : 'Rp '.number_format((float) $value, 0, ',', '.');
        $balance = $financialReports['balanceSheet'];
        $assets = $balance['assets'];
    @endphp

    <div class="alert alert-info border-0 shadow-sm">
        <i class="bi bi-info-circle me-2"></i>{{ $balance['note'] }}
    </div>

    @if (! $balance['accounting_complete'])
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Total Aset dan Total Kewajiban + Ekuitas belum dapat diverifikasi karena masih ada sumber data akuntansi yang belum tersedia.
        </div>
    @endif

    <div class="card ui-table-card mb-4">
        <div class="card-header bg-white border-0">
            <h2 class="h5 mb-0">ASET</h2>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted small">Aset Lancar Terukur</div>
                        <div class="fs-4 fw-bold text-primary">{{ $formatRupiah($assets['current_assets']) }}</div>
                        <div class="small text-muted">Persediaan yang memiliki harga beli</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted small">Kas</div>
                        <div class="fs-4 fw-bold">{{ $formatRupiah($assets['cash']) }}</div>
                        <div class="small text-muted">Saldo awal/berjalan belum dicatat sebagai akun</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted small">Aset Tetap</div>
                        <div class="fs-4 fw-bold text-primary">{{ $formatRupiah($assets['fixed_asset_value']) }}</div>
                        <div class="small text-muted">Aset dengan status Aktif</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted small">Total Aset</div>
                        <div class="fs-4 fw-bold">{{ $formatRupiah($assets['total_assets']) }}</div>
                        <div class="small text-muted">{{ $formatRupiah($assets['known_assets_total']) }} aset terukur</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card ui-table-card">
        <div class="card-header bg-white border-0">
            <h2 class="h5 mb-0">KEWAJIBAN DAN EKUITAS</h2>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted small">Kewajiban</div>
                        <div class="fs-4 fw-bold">{{ $formatRupiah($balance['liabilities']['total']) }}</div>
                        <div class="small text-muted">Tidak ada sumber utang/kewajiban yang tercatat.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted small">Ekuitas / Modal</div>
                        <div class="fs-4 fw-bold">{{ $formatRupiah($balance['equity']['total']) }}</div>
                        <div class="small text-muted">Sumber modal belum tersedia.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted small">Total Kewajiban + Ekuitas</div>
                        <div class="fs-4 fw-bold">{{ $formatRupiah($balance['total_liabilities_equity']) }}</div>
                        <div class="small text-muted">Belum dapat dibandingkan dengan total aset.</div>
                    </div>
                </div>
            </div>
            <div class="alert alert-secondary border-0 mt-4 mb-0">
                <i class="bi bi-shield-check me-2"></i>
                Hubungan Total Aset = Total Kewajiban + Ekuitas belum dinyatakan seimbang karena data kas, kewajiban, dan modal belum lengkap.
            </div>
        </div>
    </div>
</x-app-layout>