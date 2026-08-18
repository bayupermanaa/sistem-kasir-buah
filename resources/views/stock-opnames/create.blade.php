<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Mulai Stock Opname',
            'subtitle' => 'Masukkan stok fisik untuk setiap produk aktif, lalu simpan selisihnya ke riwayat stok.',
            'icon' => 'bi-clipboard-plus',
        ])
    </x-slot>

    @php
        $formatQuantity = fn ($value) => number_format((float) $value, 3, ',', '.');
    @endphp

    <div class="ui-banner ui-surface p-4 mb-3">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 ui-badge-soft-primary px-3 py-2 rounded-pill fw-semibold mb-3">
                    <i class="bi bi-shop"></i>
                    {{ $outlet->name }}
                </div>
                <h2 class="fw-bold mb-2">Form Stock Opname</h2>
                <p class="text-muted mb-0">
                    Stok sistem sudah ditampilkan sebagai snapshot. Ubah stok fisik bila ada selisih, lalu simpan untuk menyesuaikan stok outlet utama.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="text-muted small">Jumlah produk aktif</div>
                <div class="fw-semibold fs-4">{{ number_format($stocks->count(), 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    @error('stock_opname')
        <div class="alert alert-warning border-0">{{ $message }}</div>
    @enderror

    @error('items')
        <div class="alert alert-danger border-0">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('stock.opnames.store') }}" class="card ui-table-card border-0 shadow-sm" data-submit-loading>
        @csrf

        <div class="card-body border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="opname_date">Tanggal Opname</label>
                    <input type="date" id="opname_date" name="opname_date" class="form-control @error('opname_date') is-invalid @enderror" value="{{ old('opname_date', now()->toDateString()) }}" required>
                    @error('opname_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold" for="notes">Catatan Umum</label>
                    <input type="text" id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes') }}" placeholder="Contoh: opname akhir bulan">
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-end">Stok Sistem</th>
                        <th class="text-end">Stok Fisik</th>
                        <th class="text-end">Selisih</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $index => $stock)
                        @php
                            $product = $stock->product;
                            $inputId = 'physical_quantity_'.$index;
                            $differenceId = 'difference_'.$index;
                            $systemQuantity = (float) $stock->quantity;
                            $unitSymbol = $product?->unit?->symbol;
                        @endphp
                        <tr data-opname-row data-system-quantity="{{ $systemQuantity }}">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if ($product?->image_url)
                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="ui-product-thumb">
                                    @else
                                        <div class="ui-product-thumb-placeholder">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-semibold">{{ $product?->name }}</div>
                                        <div class="text-muted small">{{ $product?->code }} &middot; {{ $product?->unit?->name }} ({{ $unitSymbol }})</div>
                                    </div>
                                </div>
                                <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $product?->id }}">
                            </td>
                            <td class="text-end fw-semibold">{{ $formatQuantity($systemQuantity) }}</td>
                            <td class="text-end" style="min-width: 11rem;">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    name="items[{{ $index }}][physical_quantity]"
                                    id="{{ $inputId }}"
                                    class="form-control text-end @error('items.'.$index.'.physical_quantity') is-invalid @enderror"
                                    value="{{ old('items.'.$index.'.physical_quantity', number_format($systemQuantity, 3, '.', '')) }}"
                                    data-opname-input
                                    data-difference-target="{{ $differenceId }}"
                                    data-system-quantity="{{ $systemQuantity }}"
                                    required
                                >
                                @error('items.'.$index.'.physical_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                            <td class="text-end" style="min-width: 9rem;">
                                <span id="{{ $differenceId }}" class="badge bg-secondary" data-difference-output>Sesuai</span>
                            </td>
                            <td style="min-width: 14rem;">
                                <input
                                    type="text"
                                    name="items[{{ $index }}][notes]"
                                    class="form-control @error('items.'.$index.'.notes') is-invalid @enderror"
                                    value="{{ old('items.'.$index.'.notes') }}"
                                    placeholder="Opsional"
                                >
                                @error('items.'.$index.'.notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="ui-empty-state ui-empty-state-panel my-2">
                                    <i class="bi bi-box-seam"></i>
                                    <div class="fw-semibold">Belum ada produk aktif</div>
                                    <div class="text-muted small">Aktifkan produk di Barang & Stok terlebih dahulu agar bisa diopname.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stocks->isNotEmpty())
            <div class="card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="text-muted small">
                    Preview selisih akan berubah otomatis saat stok fisik diperbarui.
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('stock.opnames.index') }}" class="btn btn-outline-secondary ui-action-icon-btn">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>
                    <button type="submit" class="btn btn-primary ui-action-icon-btn">
                        <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true" data-submit-spinner></span>
                        <i class="bi bi-check2-circle"></i>
                        Simpan Stock Opname
                    </button>
                </div>
            </div>
        @endif
    </form>

    @if ($stocks->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const formatQuantity = (value) => Number(value || 0).toLocaleString('id-ID', {
                    minimumFractionDigits: 3,
                    maximumFractionDigits: 3,
                });

                const updateDifference = (row) => {
                    const input = row.querySelector('[data-opname-input]');
                    const target = row.querySelector('[data-difference-output]');
                    const systemQuantity = Number(row.dataset.systemQuantity || 0);
                    const physicalQuantity = Number(input.value || 0);
                    const difference = Math.round((physicalQuantity - systemQuantity) * 1000) / 1000;

                    target.classList.remove('bg-secondary', 'bg-success', 'bg-danger');

                    if (difference > 0) {
                        target.classList.add('bg-success');
                        target.textContent = `+${formatQuantity(difference)}`;
                        return;
                    }

                    if (difference < 0) {
                        target.classList.add('bg-danger');
                        target.textContent = formatQuantity(difference);
                        return;
                    }

                    target.classList.add('bg-secondary');
                    target.textContent = 'Sesuai';
                };

                document.querySelectorAll('[data-opname-row]').forEach((row) => {
                    const input = row.querySelector('[data-opname-input]');
                    updateDifference(row);
                    input.addEventListener('input', () => updateDifference(row));
                });
            });
        </script>
    @endif
</x-app-layout>
