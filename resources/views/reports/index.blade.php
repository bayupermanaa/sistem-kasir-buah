<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', ['title' => 'Laporan', 'subtitle' => 'Pusat informasi kinerja dan kondisi keuangan toko.', 'icon' => 'bi-bar-chart-line'])
    </x-slot>
    <div class="ui-surface ui-banner p-4 p-lg-5 mb-4">
        <div class="d-flex align-items-start gap-3">
            <div class="ui-page-header-icon"><i class="bi bi-bar-chart-line fs-4"></i></div>
            <div><h2 class="h4 fw-bold mb-1">Pusat Laporan</h2><p class="text-muted mb-0">Pilih laporan yang ingin ditinjau. Data mengikuti akses dan periode yang tersedia.</p></div>
        </div>
    </div>
    <div class="row g-4">
        @php $reports = [
            ['title'=>'Laporan Penjualan','description'=>'Melihat ringkasan dan detail transaksi penjualan.','icon'=>'bi-graph-up-arrow','route'=>'reports.sales','class'=>'ui-report-card-primary'],
            ['title'=>'Laporan Pengeluaran','description'=>'Melihat seluruh biaya operasional usaha.','icon'=>'bi-wallet2','route'=>'reports.expenses','class'=>''],
            ['title'=>'Laporan Laba Rugi','description'=>'Melihat pendapatan, HPP, biaya operasional, dan laba bersih.','icon'=>'bi-calculator','route'=>'reports.profit-loss','class'=>''],
            ['title'=>'Laporan Arus Kas','description'=>'Melihat kas masuk dan kas keluar usaha.','icon'=>'bi-arrow-left-right','route'=>'reports.cash-flow','class'=>''],
            ['title'=>'Neraca','description'=>'Melihat posisi aset usaha.','icon'=>'bi-clipboard-data','route'=>'reports.balance-sheet','class'=>''],
        ]; @endphp
        @foreach ($reports as $report)
            @continue($report['route'] !== 'reports.sales' && auth()->user()?->role !== 'owner')
            <div class="col-md-6 col-xl-4"><div class="card ui-report-card h-100 {{ $report['class'] }}"><div class="card-body d-flex flex-column"><div class="ui-report-icon mb-3"><i class="bi {{ $report['icon'] }}"></i></div><h2 class="h5 fw-bold">{{ $report['title'] }}</h2><p class="text-muted flex-grow-1">{{ $report['description'] }}</p><a class="btn btn-primary align-self-start" href="{{ route($report['route']) }}"><i class="bi bi-arrow-right me-1"></i>Lihat Laporan</a></div></div></div>
        @endforeach
    </div>
</x-app-layout>