<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Neraca',
            'subtitle' => 'Posisi aset, kewajiban, dan ekuitas berdasarkan saldo ledger.',
            'icon' => 'bi-clipboard-data',
        ])
    </x-slot>

    @include('reports.partials.back-link')
    @include('reports.partials.financial-filter')

    @php
        $formatRupiah = fn ($value) => $value === null ? 'Belum tersedia' : 'Rp '.number_format((float) $value, 0, ',', '.');
        $balance = $financialReports['balanceSheet'];
        $assets = $balance['assets'];
        $equity = $balance['equity'];
    @endphp

    <div class="alert alert-info border-0 shadow-sm">
        <i class="bi bi-info-circle me-2"></i>{{ $balance['note'] }}
        <span class="d-block small mt-1">Posisi per {{ \Illuminate\Support\Carbon::parse($balance['as_of_date'])->translatedFormat('d/m/Y') }}.</span>
    </div>

    @if (! $balance['accounting_active'])
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Neraca lengkap belum tersedia sebelum saldo awal dan tanggal cutover ditetapkan.
        </div>
    @elseif (! $balance['hpp_complete'])
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Laba berjalan belum dapat dihitung karena HPP belum lengkap. Sistem tidak membuat estimasi HPP.
            @if ($balance['missing_hpp_products'])
                <span class="d-block small mt-1">Produk: {{ implode(', ', $balance['missing_hpp_products']) }}</span>
            @endif
        </div>
    @endif

    @if ($balance['accounting_active'] && $balance['balanced'])
        <div class="alert alert-success border-0 shadow-sm">
            <i class="bi bi-check-circle me-2"></i>Neraca seimbang: Total Aset sama dengan Total Kewajiban + Ekuitas.
        </div>
    @elseif ($balance['accounting_active'])
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="bi bi-exclamation-octagon me-2"></i>
            Neraca belum seimbang atau belum lengkap. Selisih: {{ $formatRupiah($balance['difference']) }}.
        </div>
    @endif

    @if ($balance['missing'])
        <div class="alert alert-secondary border-0 shadow-sm">
            <strong>Data yang perlu diperiksa:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($balance['missing'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card ui-table-card mb-4">
        <div class="card-header bg-white border-0"><h2 class="h5 mb-0">ASET</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3"><div class="ui-surface p-3 h-100"><div class="text-muted small">Kas Tunai</div><div class="fs-4 fw-bold text-primary">{{ $formatRupiah($assets['cash']) }}</div></div></div>
                <div class="col-md-6 col-xl-3"><div class="ui-surface p-3 h-100"><div class="text-muted small">Bank / QRIS</div><div class="fs-4 fw-bold text-primary">{{ $formatRupiah($assets['bank']) }}</div></div></div>
                <div class="col-md-6 col-xl-3"><div class="ui-surface p-3 h-100"><div class="text-muted small">Persediaan</div><div class="fs-4 fw-bold text-primary">{{ $formatRupiah($assets['inventory_value']) }}</div></div></div>
                <div class="col-md-6 col-xl-3"><div class="ui-surface p-3 h-100"><div class="text-muted small">Aset Tetap</div><div class="fs-4 fw-bold text-primary">{{ $formatRupiah($assets['fixed_asset_value']) }}</div></div></div>
            </div>
            <div class="d-flex justify-content-between border-top mt-4 pt-3 fw-bold"><span>Total Aset</span><span>{{ $formatRupiah($assets['total_assets']) }}</span></div>
        </div>
    </div>

    <div class="card ui-table-card">
        <div class="card-header bg-white border-0"><h2 class="h5 mb-0">KEWAJIBAN DAN EKUITAS</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><div class="ui-surface p-3 h-100"><div class="text-muted small">Kewajiban / Utang Supplier</div><div class="fs-4 fw-bold">{{ $formatRupiah($balance['liabilities']['total']) }}</div></div></div>
                <div class="col-md-4"><div class="ui-surface p-3 h-100"><div class="text-muted small">Modal Pemilik</div><div class="fs-4 fw-bold">{{ $formatRupiah($equity['capital']) }}</div></div></div>
                <div class="col-md-4"><div class="ui-surface p-3 h-100"><div class="text-muted small">Laba / Rugi Berjalan</div><div class="fs-4 fw-bold">{{ $formatRupiah($equity['current_profit']) }}</div></div></div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-6"><div class="d-flex justify-content-between border-top pt-3 fw-bold"><span>Total Ekuitas</span><span>{{ $formatRupiah($equity['total']) }}</span></div></div>
                <div class="col-md-6"><div class="d-flex justify-content-between border-top pt-3 fw-bold"><span>Total Kewajiban + Ekuitas</span><span>{{ $formatRupiah($balance['total_liabilities_equity']) }}</span></div></div>
            </div>
            @if ($balance['difference'] !== null)
                <div class="d-flex justify-content-between mt-3 p-3 rounded bg-light"><span>Selisih Neraca</span><strong>{{ $formatRupiah($balance['difference']) }}</strong></div>
            @endif
        </div>
    </div>
</x-app-layout>
