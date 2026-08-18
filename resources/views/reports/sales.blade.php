<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Laporan Penjualan',
            'subtitle' => 'Ringkasan omzet, produk terlaris, performa kasir, dan tren penjualan harian.',
            'icon' => 'bi-graph-up-arrow',
        ])
    </x-slot>

    @php
        $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $formatTanggal = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '-';
        $trendMaxSales = max(1, (float) ($dailyTrends->max('total_sales') ?? 0));
    @endphp

    @if (! $hasCompletedTransactions)
        <div class="alert alert-info border-0 shadow-sm">
            Belum ada transaksi completed untuk filter yang dipilih.
        </div>
    @endif

    <div class="ui-surface ui-banner p-3 p-lg-4 mb-3">
        <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center">
            <div>
                <div class="text-muted small mb-1">Laporan untuk</div>
                <div class="fw-bold fs-5">{{ $storeName }}</div>
                <div class="text-muted small">Semua data mengikuti filter dan authorization yang aktif.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('reports.sales.export.pdf', request()->query()) }}" class="btn btn-outline-danger">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                </a>
                <a href="{{ route('reports.sales.export.excel', request()->query()) }}" class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
                </a>
            </div>
        </div>

        <hr class="ui-soft-divider my-3">

        <form method="GET" action="{{ route('reports.sales') }}" class="row g-3 align-items-end" data-submit-loading>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="preset">Preset</label>
                <select id="preset" name="preset" class="form-select">
                    <option value="">Pilih preset</option>
                    <option value="today" @selected(($filters['preset'] ?? null) === 'today')>Hari Ini</option>
                    <option value="7days" @selected(($filters['preset'] ?? null) === '7days')>7 Hari Terakhir</option>
                    <option value="month" @selected(($filters['preset'] ?? null) === 'month')>Bulan Ini</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="date_from">Dari</label>
                <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="date_to">Sampai</label>
                <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="cashier_id">Kasir</label>
                <select id="cashier_id" name="cashier_id" class="form-select">
                    <option value="">Semua kasir</option>
                    @foreach ($cashiers as $cashier)
                        <option value="{{ $cashier->id }}" @selected((string) ($filters['cashier_id'] ?? '') === (string) $cashier->id)>
                            {{ $cashier->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-funnel me-1"></i>Terapkan Filter
                </button>
                <a href="{{ route('reports.sales') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>
        </form>

        <div class="text-muted small mt-3">
            Laporan hanya menghitung transaksi berstatus <span class="fw-semibold">completed</span>.
        </div>
    </div>

    <div class="ui-surface ui-filter-card p-2 p-lg-3 mb-3">
        <div class="d-flex flex-wrap gap-2">
            <a href="#ringkasan-penjualan" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-bar-chart-line me-1"></i>Ringkasan Penjualan
            </a>
            @if ($financialAccessible)
                <a href="#laba-rugi" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-graph-up-arrow me-1"></i>Laba Rugi
                </a>
                <a href="#arus-kas" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-cash-stack me-1"></i>Arus Kas
                </a>
                <a href="#neraca" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-journal-text me-1"></i>Neraca
                </a>
            @else
                <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2">
                    <i class="bi bi-shield-lock me-1"></i>Laporan keuangan sensitif hanya untuk owner
                </span>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3" id="ringkasan-penjualan">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Total Transaksi</span>
                        <span class="ui-kpi-icon"><i class="bi bi-receipt"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((int) $summary['total_transactions'], 0, ',', '.') }} transaksi</div>
                    <div class="ui-summary-note">Transaksi completed</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Total Omzet</span>
                        <span class="ui-kpi-icon"><i class="bi bi-cash-coin"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ $formatRupiah($summary['total_sales']) }}</div>
                    <div class="ui-summary-note">Akumulasi penjualan</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Total Diskon</span>
                        <span class="ui-kpi-icon"><i class="bi bi-tag"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ $formatRupiah($summary['total_discount']) }}</div>
                    <div class="ui-summary-note">Diskon dari transaksi terpilih</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Total Berat / Item</span>
                        <span class="ui-kpi-icon"><i class="bi bi-box"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format((float) $summary['total_weight'], 3, ',', '.') }} kg</div>
                    <div class="ui-summary-note">{{ number_format((int) $summary['total_item_lines'], 0, ',', '.') }} item terjual</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Rata-rata Transaksi</span>
                        <span class="ui-kpi-icon"><i class="bi bi-graph-up-arrow"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ $formatRupiah($summary['average_transaction']) }}</div>
                    <div class="ui-summary-note">Nilai rata-rata per transaksi</div>
                </div>
            </div>
        </div>
    </div>

    @if ($financialAccessible)
        <div class="row g-3 mb-3">
            <div class="col-12" id="laba-rugi">
                <div class="card ui-table-card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pb-0">
                        <h2 class="h5 mb-1">Laporan Laba Rugi</h2>
                        <div class="text-muted small">Menggunakan transaksi completed, harga beli produk saat ini, dan pengeluaran periode terpilih.</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-xl-4">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">Pendapatan Penjualan</div>
                                    <div class="fw-bold fs-4">{{ $formatRupiah($financialReports['profitLoss']['revenue_before_discount']) }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">(-) Diskon Penjualan</div>
                                    <div class="fw-bold fs-4 text-danger">-{{ $formatRupiah($financialReports['profitLoss']['discount_total']) }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">Penjualan Bersih</div>
                                    <div class="fw-bold fs-4 text-success">{{ $formatRupiah($financialReports['profitLoss']['net_sales']) }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">(-) Harga Pokok Penjualan</div>
                                    <div class="fw-bold fs-4 text-danger">-{{ $formatRupiah($financialReports['profitLoss']['hpp']) }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">Laba Kotor</div>
                                    <div class="fw-bold fs-4 {{ $financialReports['profitLoss']['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $formatRupiah($financialReports['profitLoss']['gross_profit']) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">(-) Pengeluaran Operasional</div>
                                    <div class="fw-bold fs-4 text-danger">-{{ $formatRupiah($financialReports['profitLoss']['operating_expenses']) }}</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="ui-surface p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small">Laba Bersih</div>
                                            <div class="fw-bold fs-3 {{ $financialReports['profitLoss']['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $formatRupiah($financialReports['profitLoss']['net_profit']) }}
                                            </div>
                                        </div>
                                        <span class="badge {{ $financialReports['profitLoss']['net_profit'] >= 0 ? 'bg-success' : 'bg-danger' }} fs-6">
                                            {{ $financialReports['profitLoss']['net_profit'] >= 0 ? 'Positif' : 'Negatif' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted small mt-3">
                            Catatan: HPP memakai harga beli produk saat ini, sehingga belum menjadi histori akuntansi yang sepenuhnya tersnapshot.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12" id="arus-kas">
                <div class="card ui-table-card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pb-0">
                        <h2 class="h5 mb-1">Arus Kas</h2>
                        <div class="text-muted small">Kas masuk dihitung dari transaksi completed berdasarkan metode pembayaran. Pengeluaran menjadi kas keluar.</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-xl-3">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">Kas Masuk - Tunai</div>
                                    <div class="fw-bold fs-4 text-success">{{ $formatRupiah($financialReports['cashFlow']['cash_in_cash']) }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">Kas Masuk - QRIS</div>
                                    <div class="fw-bold fs-4 text-success">{{ $formatRupiah($financialReports['cashFlow']['cash_in_qris']) }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">Total Kas Masuk</div>
                                    <div class="fw-bold fs-4">{{ $formatRupiah($financialReports['cashFlow']['cash_in_total']) }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="ui-surface p-3 h-100">
                                    <div class="text-muted small mb-1">Total Kas Keluar</div>
                                    <div class="fw-bold fs-4 text-danger">-{{ $formatRupiah($financialReports['cashFlow']['cash_out_expenses']) }}</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="ui-surface p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small">Arus Kas Bersih</div>
                                            <div class="fw-bold fs-3 {{ $financialReports['cashFlow']['cash_flow_net'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $formatRupiah($financialReports['cashFlow']['cash_flow_net']) }}
                                            </div>
                                        </div>
                                        <span class="badge {{ $financialReports['cashFlow']['cash_flow_net'] >= 0 ? 'bg-success' : 'bg-danger' }} fs-6">
                                            {{ $financialReports['cashFlow']['cash_flow_net'] >= 0 ? 'Masuk' : 'Keluar' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($financialReports['cashFlow']['expense_by_category']->isNotEmpty())
                            <div class="table-responsive mt-4">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Kategori Pengeluaran</th>
                                            <th class="text-end">Jumlah</th>
                                            <th class="text-end">Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($financialReports['cashFlow']['expense_by_category'] as $row)
                                            <tr>
                                                <td>{{ $row->category }}</td>
                                                <td class="text-end">{{ number_format((int) $row->expense_count, 0, ',', '.') }}</td>
                                                <td class="text-end">{{ $formatRupiah($row->total_amount) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12" id="neraca">
                <div class="card ui-table-card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pb-0">
                        <h2 class="h5 mb-1">Neraca</h2>
                        <div class="text-muted small">Audit data menentukan apakah neraca bisa ditampilkan dengan benar.</div>
                    </div>
                    <div class="card-body">
                        @if ($financialReports['balanceSheet']['available'])
                            <div class="alert alert-success border-0 mb-0">
                                Neraca tersedia.
                            </div>
                        @else
                            <div class="alert alert-warning border-0 mb-3">
                                {{ $financialReports['balanceSheet']['note'] }}
                            </div>
                            <div class="row g-2">
                                @foreach ($financialReports['balanceSheet']['missing'] as $missingItem)
                                    <div class="col-md-6 col-lg-4">
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis w-100 text-start px-3 py-2">
                                            <i class="bi bi-dash-circle me-1"></i>{{ $missingItem }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info border-0 shadow-sm">
            Laporan keuangan sensitif dibatasi untuk owner. Cashier tetap dapat melihat ringkasan penjualan yang sudah ada.
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="card ui-table-card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h2 class="h5 mb-1">Tren Penjualan Harian</h2>
                    <div class="text-muted small">Grafik sederhana total omzet dan jumlah transaksi per hari.</div>
                </div>
                <div class="card-body">
                    @forelse ($dailyTrends as $trend)
                        @php
                            $percentage = max(8, ((float) $trend->total_sales / $trendMaxSales) * 100);
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold">{{ $formatTanggal($trend->report_date) }}</span>
                                <span class="text-muted">{{ number_format((int) $trend->transaction_count, 0, ',', '.') }} transaksi · {{ $formatRupiah($trend->total_sales) }}</span>
                            </div>
                            <div class="progress" style="height: 1rem;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $percentage }}%;" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    @empty
                        <div class="ui-empty-state">
                            <i class="bi bi-graph-up"></i>
                            <div class="fw-semibold">Belum ada data tren penjualan.</div>
                            <div class="text-muted small">Grafik akan tampil setelah ada transaksi completed.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card ui-table-card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h2 class="h5 mb-1">Produk Terlaris</h2>
                    <div class="text-muted small">Top 10 produk berdasarkan omzet dan berat terjual.</div>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Produk</th>
                                <th>Kategori</th>
                                <th class="text-end">Berat</th>
                                <th class="text-end">Transaksi</th>
                                <th class="text-end">Omzet</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topProducts as $product)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td class="fw-semibold">{{ $product->name }}</td>
                                    <td>{{ $product->category_name }}</td>
                                    <td class="text-end">{{ number_format((float) $product->total_weight, 3, ',', '.') }} kg</td>
                                    <td class="text-end">{{ number_format((int) $product->transaction_count, 0, ',', '.') }}</td>
                                    <td class="text-end">{{ $formatRupiah($product->total_sales) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="ui-empty-state">
                                            <i class="bi bi-basket"></i>
                                            <div class="fw-semibold">Belum ada produk terjual.</div>
                                            <div class="text-muted small">Data akan muncul setelah transaksi completed tersedia.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card ui-table-card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pb-0">
            <h2 class="h5 mb-1">Performa Kasir</h2>
            <div class="text-muted small">Rekap performa kasir berdasarkan transaksi completed.</div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kasir</th>
                        <th>Toko</th>
                        <th class="text-end">Jumlah Transaksi</th>
                        <th class="text-end">Total Omzet</th>
                        <th class="text-end">Rata-rata Transaksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cashierPerformance as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $row->cashier_name }}</td>
                            <td>{{ $storeName }}</td>
                            <td class="text-end">{{ number_format((int) $row->transaction_count, 0, ',', '.') }}</td>
                            <td class="text-end">{{ $formatRupiah($row->total_sales) }}</td>
                            <td class="text-end">{{ $formatRupiah($row->average_sales) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="ui-empty-state">
                                    <i class="bi bi-person-badge"></i>
                                    <div class="fw-semibold">Belum ada performa kasir untuk ditampilkan.</div>
                                    <div class="text-muted small">Data performa kasir akan tampil setelah transaksi masuk.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
