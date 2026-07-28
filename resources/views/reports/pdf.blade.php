@php
    $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $formatTanggal = fn ($value) => \Illuminate\Support\Carbon::parse($value)->translatedFormat('d/m/Y');
    $formatTanggalJam = fn ($value) => \Illuminate\Support\Carbon::parse($value)->translatedFormat('d/m/Y H:i');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18px 20px 42px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1, h2, h3, p { margin: 0; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .muted { color: #6b7280; }
        .header { margin-bottom: 12px; }
        .brand { font-size: 18px; font-weight: 700; }
        .subtitle { font-size: 11px; margin-top: 3px; }
        .meta { margin-top: 8px; line-height: 1.55; }
        .section { margin-top: 12px; }
        .section-title { font-size: 12px; font-weight: 700; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-size: 9px; }
        .summary td { width: 25%; }
        .summary .label { font-weight: 700; width: 42%; }
        .summary .value { text-align: right; }
        .footer { position: fixed; left: 0; right: 0; bottom: -18px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <div class="text-center">
            <div class="brand">Sistem Kasir</div>
            <div class="subtitle">Toko Buah Teras Fatimah</div>
            <div class="subtitle">Laporan Penjualan</div>
        </div>

        <div class="meta">
            <div><strong>Periode:</strong> {{ $reportPeriod }}</div>
            <div><strong>Dicetak:</strong> {{ $formatTanggalJam($printedAt) }}</div>
            <div><strong>Toko:</strong> {{ $storeName }}</div>
            <div><strong>Kasir:</strong> {{ $selectedCashierName ?? 'Semua kasir' }}</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Ringkasan</div>
        <table class="summary">
            <tr>
                <td class="label">Total Transaksi</td>
                <td class="value">{{ number_format((int) $summary['total_transactions'], 0, ',', '.') }}</td>
                <td class="label">Total Omzet</td>
                <td class="value">{{ $formatRupiah($summary['total_sales']) }}</td>
            </tr>
            <tr>
                <td class="label">Total Diskon</td>
                <td class="value">{{ $formatRupiah($summary['total_discount']) }}</td>
                <td class="label">Total Berat / Item</td>
                <td class="value">{{ number_format((float) $summary['total_weight'], 3, ',', '.') }} kg / {{ number_format((int) $summary['total_item_lines'], 0, ',', '.') }} item</td>
            </tr>
            <tr>
                <td class="label">Rata-rata Transaksi</td>
                <td class="value">{{ $formatRupiah($summary['average_transaction']) }}</td>
                <td class="label">Jumlah Produk Terlaris</td>
                <td class="value">{{ number_format((int) $topProducts->count(), 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Produk Terlaris</div>
        <table>
            <thead>
                <tr>
                    <th style="width:5%;">No</th>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th class="text-right" style="width:14%;">Berat / Item</th>
                    <th class="text-right" style="width:14%;">Transaksi</th>
                    <th class="text-right" style="width:16%;">Omzet</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($topProducts as $product)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category_name }}</td>
                        <td class="text-right">{{ number_format((float) $product->total_weight, 3, ',', '.') }} kg</td>
                        <td class="text-right">{{ number_format((int) $product->transaction_count, 0, ',', '.') }}</td>
                        <td class="text-right">{{ $formatRupiah($product->total_sales) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center muted">Belum ada produk terjual.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Performa Kasir</div>
        <table>
            <thead>
                <tr>
                    <th style="width:5%;">No</th>
                    <th>Kasir</th>
                    <th>Toko</th>
                    <th class="text-right" style="width:15%;">Transaksi</th>
                    <th class="text-right" style="width:16%;">Omzet</th>
                    <th class="text-right" style="width:16%;">Rata-rata</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cashierPerformance as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row->cashier_name }}</td>
                        <td>{{ $row->store_name }}</td>
                        <td class="text-right">{{ number_format((int) $row->transaction_count, 0, ',', '.') }}</td>
                        <td class="text-right">{{ $formatRupiah($row->total_sales) }}</td>
                        <td class="text-right">{{ $formatRupiah($row->average_sales) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center muted">Belum ada performa kasir.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Tren Harian</div>
        <table>
            <thead>
                <tr>
                    <th style="width:25%;">Tanggal</th>
                    <th class="text-right" style="width:20%;">Jumlah Transaksi</th>
                    <th class="text-right" style="width:20%;">Omzet</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($dailyTrends as $trend)
                    <tr>
                        <td>{{ $formatTanggal($trend->report_date) }}</td>
                        <td class="text-right">{{ number_format((int) $trend->transaction_count, 0, ',', '.') }}</td>
                        <td class="text-right">{{ $formatRupiah($trend->total_sales) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center muted">Belum ada data tren harian.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        <div class="text-center">Terima kasih.</div>
        <script type="text/php">
            if (isset($pdf)) {
                $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                $pdf->page_text(740, 560, "Halaman {PAGE_NUM} / {PAGE_COUNT}", $font, 8, array(0,0,0));
            }
        </script>
    </div>
</body>
</html>
