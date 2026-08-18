<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Dashboard Pemilik',
            'subtitle' => 'Pusat navigasi cepat untuk aktivitas operasional toko.',
            'icon' => 'bi-speedometer2',
        ])
    </x-slot>

    <div class="ui-surface ui-banner p-4 p-lg-5 mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 ui-badge-soft-primary px-3 py-2 rounded-pill fw-semibold mb-3">
                    <i class="bi bi-shop"></i>
                    Toko Buah Teras Fatimah
                </div>
                <h2 class="fw-bold mb-2">Selamat datang di Sistem Kasir</h2>
                <p class="text-muted mb-0">
                    Gunakan dashboard ini sebagai pintu cepat menuju kasir, stok, riwayat transaksi, dan laporan penjualan.
                    Tampilan ini sengaja dibuat sederhana agar tetap fokus pada aktivitas utama toko.
                </p>
            </div>
            <div class="col-lg-5">
                <div class="ui-surface bg-white p-3 p-lg-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="ui-quick-action-icon">
                            <i class="bi bi-stars fs-4"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Dashboard siap digunakan</div>
                            <div class="text-muted small">Tidak ada angka statis palsu. Data operasional akan tampil dari modul yang sudah tersedia.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('kasir') }}" class="card ui-quick-link-card ui-quick-action border-0 shadow-sm">
                <div class="card-body">
                    <div class="ui-quick-action-icon">
                        <i class="bi bi-cash-coin fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Kasir</div>
                        <div class="text-muted small">Mulai transaksi baru</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="{{ route('products.index') }}" class="card ui-quick-link-card ui-quick-action border-0 shadow-sm">
                <div class="card-body">
                    <div class="ui-quick-action-icon">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Barang & Stok</div>
                        <div class="text-muted small">Lihat produk dan persediaan</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="{{ route('transactions.index') }}" class="card ui-quick-link-card ui-quick-action border-0 shadow-sm">
                <div class="card-body">
                    <div class="ui-quick-action-icon">
                        <i class="bi bi-receipt-cutoff fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Riwayat Transaksi</div>
                        <div class="text-muted small">Tinjau transaksi tersimpan</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="{{ route('reports.sales') }}" class="card ui-quick-link-card ui-quick-action border-0 shadow-sm">
                <div class="card-body">
                    <div class="ui-quick-action-icon">
                        <i class="bi bi-graph-up-arrow fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Laporan Penjualan</div>
                        <div class="text-muted small">Pantau omzet dan tren</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="{{ route('expenses.index') }}" class="card ui-quick-link-card ui-quick-action border-0 shadow-sm">
                <div class="card-body">
                    <div class="ui-quick-action-icon">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Pengeluaran</div>
                        <div class="text-muted small">Catat biaya operasional</div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</x-app-layout>
