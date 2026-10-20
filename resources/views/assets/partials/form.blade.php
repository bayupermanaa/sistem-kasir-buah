@php
    $asset = $asset ?? null;
    $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp

<form method="POST" action="{{ $action }}" data-submit-loading>
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <div class="ui-form-section">
        <div class="ui-form-section-heading">
            <div class="ui-form-section-icon">
                <i class="bi bi-safe2"></i>
            </div>
            <div>
                <h2>Informasi Aset</h2>
                <p>Seluruh aset terikat pada toko utama dan disimpan server-side.</p>
            </div>
        </div>

        <div class="alert alert-info d-flex align-items-start gap-2" role="note">
            <i class="bi bi-info-circle mt-1"></i>
            <div>Aset historis yang sudah termasuk saldo awal tidak perlu didaftarkan ulang sebagai pembelian baru.</div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Nama Aset</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $asset->name ?? '') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="category">Kategori</label>
                <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(old('category', $asset->category ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="purchase_date">Tanggal Pembelian</label>
                <input type="date" name="purchase_date" id="purchase_date" class="form-control @error('purchase_date') is-invalid @enderror" value="{{ old('purchase_date', $asset?->purchase_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                @error('purchase_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="purchase_price">Harga Satuan</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" inputmode="numeric" min="0.01" step="0.01" name="purchase_price" id="purchase_price" class="form-control rupiah-input @error('purchase_price') is-invalid @enderror" value="{{ old('purchase_price', $asset->purchase_price ?? '') }}" required>
                    @error('purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="quantity">Jumlah</label>
                <input type="number" min="1" step="1" name="quantity" id="quantity" class="form-control @error('quantity') is-invalid @enderror" value="{{ old('quantity', $asset->quantity ?? 1) }}" required>
                @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
                <label class="form-label" for="total_cost">Total Biaya</label>
                <input type="text" id="total_cost" class="form-control" value="{{ old('total_cost', isset($asset) ? $formatRupiah($asset->total_cost) : 'Rp 0') }}" readonly>
                <div class="form-text">Total dihitung otomatis dari harga satuan × jumlah.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="payment_method">Metode Pembayaran</label>
                <select name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                    <option value="">Pilih metode</option>
                    @foreach ($paymentMethods as $key => $label)
                        <option value="{{ $key }}" @selected(old('payment_method', $asset->payment_method ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-text">Pembelian aset dicatat sebagai aset tetap, bukan beban operasional.</div>
                @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="">Pilih status</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status }}" @selected(old('status', $asset->status ?? 'Aktif') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="useful_life_years">Masa Manfaat (Tahun)</label>
                <input type="number" min="1" step="1" name="useful_life_years" id="useful_life_years" class="form-control @error('useful_life_years') is-invalid @enderror" value="{{ old('useful_life_years', $asset->useful_life_years ?? '') }}">
                @error('useful_life_years')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="residual_value">Nilai Residu</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" inputmode="numeric" min="0" step="0.01" name="residual_value" id="residual_value" class="form-control rupiah-input @error('residual_value') is-invalid @enderror" value="{{ old('residual_value', $asset->residual_value ?? 0) }}">
                    @error('residual_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Catatan</label>
                <textarea name="notes" id="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" placeholder="Opsional">{{ old('notes', $asset->notes ?? '') }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
        <div class="text-muted small">
            Created by akan tersimpan otomatis dari user login.
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('assets.index') }}" class="btn btn-outline-secondary ui-action-icon-btn">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
            <button type="submit" class="btn btn-primary ui-action-icon-btn" data-submit-loading-button>
                <i class="bi bi-check2-circle"></i>
                {{ $buttonLabel }}
            </button>
        </div>
    </div>
</form>

@pushOnce('scripts')
<script>
(() => {
    const priceInput = document.getElementById('purchase_price');
    const quantityInput = document.getElementById('quantity');
    const totalInput = document.getElementById('total_cost');

    const formatRupiah = (value) => 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round(value || 0));

    const updateTotal = () => {
        const price = parseFloat(priceInput?.value || '0');
        const quantity = parseInt(quantityInput?.value || '0', 10);
        const total = (Number.isFinite(price) ? price : 0) * (Number.isFinite(quantity) ? quantity : 0);
        if (totalInput) {
            totalInput.value = formatRupiah(total);
        }
    };

    priceInput?.addEventListener('input', updateTotal);
    quantityInput?.addEventListener('input', updateTotal);
    updateTotal();
})();
</script>
@endPushOnce
