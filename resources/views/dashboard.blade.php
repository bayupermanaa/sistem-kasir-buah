@php
    $formatRupiah = fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.');
    $maxTrendSales = max(1, (float) $salesTrend->max('total_sales'));
@endphp

<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Dashboard Pemilik',
            'subtitle' => 'Ringkasan operasional '.($outlet->name ?? 'toko').'.',
            'icon' => 'bi-speedometer2',
        ])
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card ui-stat-card h-100 border-0">
                <div class="card-body">
                    <div class="ui-kpi-icon mb-3"><i class="bi bi-cash-stack"></i></div>
                    <div class="ui-summary-label">Penjualan Hari Ini</div>
                    <div class="ui-summary-value">{{ $formatRupiah($todaySales) }}</div>
                    <div class="ui-summary-note">Transaksi selesai</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card ui-stat-card h-100 border-0">
                <div class="card-body">
                    <div class="ui-kpi-icon mb-3"><i class="bi bi-receipt"></i></div>
                    <div class="ui-summary-label">Transaksi Hari Ini</div>
                    <div class="ui-summary-value">{{ number_format($todayTransactionCount, 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Tidak menghitung item ganda</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card ui-stat-card h-100 border-0">
                <div class="card-body">
                    <div class="ui-kpi-icon mb-3"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="ui-summary-label">Stok Perlu Perhatian</div>
                    <div class="ui-summary-value">{{ number_format($lowStockCount, 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Menipis atau habis</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card ui-stat-card h-100 border-0">
                <div class="card-body">
                    <div class="ui-kpi-icon mb-3"><i class="bi bi-journal-text"></i></div>
                    <div class="ui-summary-label">Utang Supplier</div>
                    <div class="ui-summary-value">{{ $formatRupiah($outstandingPayables) }}</div>
                    <div class="ui-summary-note">Saldo outstanding</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="ui-section-card h-100">
                <div class="card-header p-3 p-lg-4">
                    <div class="ui-section-card-title">Tren Penjualan 7 Hari</div>
                    <p class="ui-section-card-subtitle">Transaksi selesai berdasarkan tanggal toko.</p>
                </div>
                <div class="card-body p-3 p-lg-4">
                    <div class="row g-3 align-items-end" aria-label="Grafik tren penjualan tujuh hari">
                        @foreach ($salesTrend as $day)
                            <div class="col">
                                <div class="d-flex flex-column align-items-center gap-2 h-100">
                                    <small class="text-muted text-center">{{ $day['transaction_count'] }}</small>
                                    <div class="w-100 d-flex align-items-end justify-content-center" style="height: 9rem;">
                                        <div class="bg-primary rounded-top w-75" style="height: {{ max(4, ($day['total_sales'] / $maxTrendSales) * 100) }}%;" title="{{ $formatRupiah($day['total_sales']) }}"></div>
                                    </div>
                                    <small class="text-muted text-center">{{ $day['date']->translatedFormat('D d/m') }}</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="text-muted small mt-3">Nilai omzet tertinggi: {{ $formatRupiah($maxTrendSales) }}</div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="ui-section-card h-100">
                <div class="card-header p-3 p-lg-4">
                    <div class="ui-section-card-title">Status Akuntansi</div>
                    <p class="ui-section-card-subtitle">Informasi kesiapan pembukuan.</p>
                </div>
                <div class="card-body p-3 p-lg-4">
                    @if ($accounting['active'])
                        <div class="alert {{ $accounting['hpp_complete'] ? 'alert-success' : 'alert-warning' }} d-flex gap-2" role="status">
                            <i class="bi {{ $accounting['hpp_complete'] ? 'bi-check-circle' : 'bi-exclamation-triangle' }}"></i>
                            <span>{{ $accounting['hpp_complete'] ? 'Accounting aktif dan HPP lengkap.' : 'Accounting aktif, tetapi HPP belum lengkap.' }}</span>
                        </div>
                    @else
                        <div class="alert alert-info d-flex gap-2" role="status">
                            <i class="bi bi-info-circle"></i>
                            <span>Accounting belum aktif.</span>
                        </div>
                    @endif
                    @if (count($accounting['messages']))
                        <ul class="small text-muted mb-0 ps-3">
                            @foreach ($accounting['messages'] as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="ui-section-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center gap-2 p-3 p-lg-4">
                    <div>
                        <div class="ui-section-card-title">Transaksi Terbaru</div>
                        <p class="ui-section-card-subtitle">Lima transaksi selesai terakhir.</p>
                    </div>
                    <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-right me-1"></i>Lihat Semua</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Invoice</th><th>Waktu</th><th>Kasir</th><th>Metode</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($recentTransactions as $transaction)
                                <tr>
                                    <td><a href="{{ route('kasir.show', $transaction) }}" class="fw-semibold text-decoration-none">{{ $transaction->invoice_number }}</a></td>
                                    <td class="text-muted small">{{ $transaction->created_at?->translatedFormat('d/m H:i') ?? '-' }}</td>
                                    <td>{{ $transaction->user?->name ?? '-' }}</td>
                                    <td><span class="badge {{ $transaction->payment_method_badge_class }}">{{ $transaction->payment_method_label }}</span></td>
                                    <td class="text-end fw-semibold">{{ $formatRupiah($transaction->total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="ui-empty-state">Belum ada transaksi selesai.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="ui-section-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center gap-2 p-3 p-lg-4">
                    <div>
                        <div class="ui-section-card-title">Peringatan Stok</div>
                        <p class="ui-section-card-subtitle">Produk yang perlu diperiksa.</p>
                    </div>
                    <a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-seam me-1"></i>Stok</a>
                </div>
                <div class="card-body p-3 p-lg-4">
                    @forelse ($stockWarnings as $stock)
                        <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
                            <div><div class="fw-semibold">{{ $stock->product?->name ?? '-' }}</div><small class="text-muted">{{ $stock->product?->code ?? '-' }}</small></div>
                            <span class="badge {{ $stock->statusBadgeClass() }}">{{ $stock->statusLabel() }} · {{ number_format((float) $stock->quantity, 3, ',', '.') }}</span>
                        </div>
                    @empty
                        <div class="ui-empty-state">Semua stok berada dalam kondisi aman.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="ui-section-card">
        <div class="card-header p-3 p-lg-4"><div class="ui-section-card-title">Akses Cepat</div><p class="ui-section-card-subtitle">Shortcut operasional untuk Owner.</p></div>
        <div class="card-body p-3 p-lg-4">
            <div class="row g-3">
                @foreach ([
                    ['route' => 'kasir', 'label' => 'Kasir', 'icon' => 'bi-cash-coin'],
                    ['route' => 'products.index', 'label' => 'Barang & Stok', 'icon' => 'bi-box-seam'],
                    ['route' => 'stocks.purchase.create', 'label' => 'Pembelian Stok', 'icon' => 'bi-cart-plus'],
                    ['route' => 'stock.opnames.index', 'label' => 'Stock Opname', 'icon' => 'bi-clipboard-check'],
                    ['route' => 'transactions.index', 'label' => 'Riwayat Transaksi', 'icon' => 'bi-receipt-cutoff'],
                    ['route' => 'reports.index', 'label' => 'Laporan', 'icon' => 'bi-bar-chart-line'],
                ] as $action)
                    @if ($action['route'] === 'stocks.purchase.create')
                        <div class="col-6 col-md-4"><a href="{{ route('products.index') }}" class="btn btn-outline-primary w-100"><i class="bi {{ $action['icon'] }} me-1"></i>{{ $action['label'] }}</a></div>
                    @else
                        <div class="col-6 col-md-4"><a href="{{ route($action['route']) }}" class="btn btn-outline-primary w-100"><i class="bi {{ $action['icon'] }} me-1"></i>{{ $action['label'] }}</a></div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
