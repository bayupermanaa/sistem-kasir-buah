<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Laporan Laba Rugi',
            'subtitle' => 'Pendapatan, HPP, beban operasional, dan laba bersih.',
            'icon' => 'bi-calculator',
        ])
    </x-slot>

    @include('reports.partials.back-link')
    @include('reports.partials.financial-filter')

    @php
        $formatRupiah = fn ($value) => $value === null ? 'Belum tersedia' : 'Rp '.number_format((float) $value, 0, ',', '.');
        $profitLoss = $financialReports['profitLoss'];
    @endphp

    <div class="alert alert-info border-0 shadow-sm">
        <i class="bi bi-info-circle me-2"></i>{{ $profitLoss['coverage_note'] }}
        <span class="d-block small mt-1">
            Sumber: {{ $profitLoss['source'] === 'accounting_ledger' ? 'Accounting Ledger' : 'Data operasional historis' }}.
            Periode {{ $profitLoss['period_from'] ?: '-' }} sampai {{ $profitLoss['period_to'] ?: '-' }}.
        </span>
    </div>

    @if (! $profitLoss['accounting_active'])
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Pembukuan belum aktif. Nilai yang ditampilkan belum merupakan Laba Rugi berbasis ledger final.
        </div>
    @endif

    @if (! $profitLoss['hpp_complete'])
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Perhitungan laba belum lengkap karena HPP belum lengkap. Pendapatan tetap ditampilkan, tetapi HPP, Laba Kotor, dan Laba/Rugi Bersih tidak dihitung sebagai angka final.
            @if ($profitLoss['missing_hpp_products'])
                <span class="d-block small mt-1">Produk tanpa harga beli: {{ $profitLoss['missing_hpp_products']->implode(', ') }}</span>
            @endif
        </div>
    @endif

    <div class="card ui-table-card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted">PENDAPATAN — PENJUALAN BERSIH</div>
                        <div class="fs-3 fw-bold text-primary">{{ $formatRupiah($profitLoss['net_sales']) }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted">HARGA POKOK PENJUALAN — HPP</div>
                        <div class="fs-3 fw-bold text-danger">{{ $profitLoss['hpp'] === null ? 'Belum dapat dihitung' : '- '.$formatRupiah($profitLoss['hpp']) }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted">LABA KOTOR</div>
                        <div class="fs-3 fw-bold">{{ $profitLoss['gross_profit'] === null ? 'Belum dapat dihitung' : $formatRupiah($profitLoss['gross_profit']) }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ui-surface p-3 h-100">
                        <div class="text-muted">BEBAN OPERASIONAL</div>
                        <div class="fs-3 fw-bold text-danger">{{ $profitLoss['operating_expenses'] === null ? 'Belum tersedia' : '- '.$formatRupiah($profitLoss['operating_expenses']) }}</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="ui-surface p-4 bg-primary-subtle">
                        <div class="text-muted">LABA / RUGI BERSIH</div>
                        <div class="fs-2 fw-bold">{{ $profitLoss['net_profit'] === null ? 'Belum dapat dihitung' : $formatRupiah($profitLoss['net_profit']) }}</div>
                        <small class="text-muted">Penjualan Bersih − HPP − Beban Operasional</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
