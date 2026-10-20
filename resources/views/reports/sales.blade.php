<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Laporan Penjualan',
            'subtitle' => 'Ringkasan omzet, produk terlaris, performa kasir, dan tren penjualan harian.',
            'icon' => 'bi-graph-up-arrow',
        ])
    </x-slot>

@include('reports.partials.back-link')

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
                <a href="{{ route('reports.sales.export.excel', request()->query()) }}" class="btn btn-outline-primary">
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
                                <span class="text-muted">{{ number_format((int) $trend->transaction_count, 0, ',', '.') }} transaksi Ã‚Â· {{ $formatRupiah($trend->total_sales) }}</span>
                            </div>
                            <div class="progress ui-progress-compact">
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
