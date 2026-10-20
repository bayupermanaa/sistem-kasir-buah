<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', ['title' => 'Laporan Arus Kas', 'subtitle' => 'Arus kas berdasarkan sumber jurnal yang dapat direkonsiliasi.', 'icon' => 'bi-arrow-left-right'])
    </x-slot>

    @include('reports.partials.back-link')
    @include('reports.partials.financial-filter')

    @php
        $formatRupiah = fn ($value) => 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
        $cf = $financialReports['cashFlow'];
        $isLedger = ($cf['source'] ?? null) === 'accounting_ledger';
    @endphp

    @if (! empty($cf['note']))
        <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert"><i class="bi bi-info-circle-fill"></i><span>{{ $cf['note'] }}</span></div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Kas Masuk Operasional', 'value' => $cf['operating_inflow'], 'class' => 'text-primary', 'icon' => 'bi-arrow-down-left-circle'],
            ['label' => 'Kas Keluar Operasional', 'value' => $cf['operating_outflow'], 'class' => 'text-danger', 'icon' => 'bi-arrow-up-right-circle'],
            ['label' => 'Perubahan Kas Bersih', 'value' => $cf['net_change'], 'class' => 'text-primary', 'icon' => 'bi-graph-up-arrow'],
        ] as $card)
            <div class="col-md-4"><div class="ui-stat-card card h-100"><div class="card-body d-flex justify-content-between gap-3"><div><span class="ui-summary-label">{{ $card['label'] }}</span><div class="ui-summary-value {{ $card['class'] }}">{{ $formatRupiah($card['value']) }}</div></div><i class="bi {{ $card['icon'] }} fs-3 text-primary"></i></div></div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        @foreach ([['label' => 'Arus Operasi', 'value' => $cf['operating_net']], ['label' => 'Arus Investasi', 'value' => $cf['investing_net']], ['label' => 'Arus Pembiayaan', 'value' => $cf['financing_net']]] as $card)
            <div class="col-md-4"><div class="card ui-surface h-100"><div class="card-body"><span class="ui-summary-label">{{ $card['label'] }}</span><div class="h4 mb-0">{{ $formatRupiah($card['value']) }}</div></div></div></div>
        @endforeach
    </div>

    @if ($isLedger)
        <div class="card ui-surface mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Rekonsiliasi Saldo Kas dan Bank</h2></div><div class="card-body"><div class="row g-3"><div class="col-md-4"><span class="ui-summary-label">Saldo Awal</span><div class="h5 mb-0">{{ $formatRupiah($cf['opening_total']) }}</div><small class="text-muted">Kas {{ $formatRupiah($cf['opening_cash']) }} · Bank {{ $formatRupiah($cf['opening_bank']) }}</small></div><div class="col-md-4"><span class="ui-summary-label">Perubahan Bersih</span><div class="h5 mb-0">{{ $formatRupiah($cf['net_change']) }}</div></div><div class="col-md-4"><span class="ui-summary-label">Saldo Akhir</span><div class="h5 mb-0">{{ $formatRupiah($cf['ending_total']) }}</div><small class="text-muted">Kas {{ $formatRupiah($cf['ending_cash']) }} · Bank {{ $formatRupiah($cf['ending_bank']) }}</small></div></div><hr><div class="d-flex flex-wrap gap-2 align-items-center"><span>Status rekonsiliasi:</span><span class="badge {{ $cf['reconciled'] ? 'bg-success' : 'bg-danger' }}">{{ $cf['reconciled'] ? 'Seimbang' : 'Perlu diperiksa' }}</span><span class="text-muted">Selisih {{ $formatRupiah($cf['difference']) }}</span></div></div></div>
    @endif

    @if (! empty($cf['unclassified']))
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-2"></i>Ada {{ count($cf['unclassified']) }} jurnal kas yang belum terklasifikasi. Nilai arus kas belum dapat dianggap final.</div>
    @endif

    <div class="card ui-table-card"><div class="card-header bg-white"><h2 class="h5 mb-0">Rincian Arus Kas</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Kelompok</th><th>Komponen</th><th class="text-end">Nominal</th></tr></thead><tbody><tr><td rowspan="2">Kas Masuk</td><td>Penjualan Tunai</td><td class="text-end">{{ $formatRupiah($cf['cash_in_cash']) }}</td></tr><tr><td>Penjualan QRIS/Bank</td><td class="text-end">{{ $formatRupiah($cf['cash_in_qris']) }}</td></tr><tr><td rowspan="4">Kas Keluar</td><td>Beban Operasional</td><td class="text-end">{{ $formatRupiah($cf['cash_out_expenses']) }}</td></tr><tr><td>Pembelian Stok Tunai</td><td class="text-end">{{ $formatRupiah($cf['cash_out_stock_purchases']) }}</td></tr><tr><td>Pembayaran Utang Supplier</td><td class="text-end">{{ $formatRupiah($cf['cash_out_payable_payments']) }}</td></tr><tr><td>Pembelian Aset</td><td class="text-end">{{ $formatRupiah($cf['cash_out_assets']) }}</td></tr></tbody></table></div></div></div>
</x-app-layout>
