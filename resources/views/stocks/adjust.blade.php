<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Penyesuaian Stok',
            'subtitle' => 'Tambah atau kurangi stok untuk satu produk pada toko utama.',
            'icon' => 'bi-arrow-left-right',
        ])
    </x-slot>

    <div class="ui-surface ui-banner p-3 p-lg-4 mb-3">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <div class="text-muted small mb-1">Produk yang disesuaikan</div>
                <div class="fw-bold fs-5">{{ $product->name }}</div>
                <div class="text-muted small">{{ $product->code }} · {{ $product->category?->name }} · {{ $product->unit?->name }}</div>
                @if ($stock)
                    <div class="mt-2">
                        <span class="badge {{ $stock->statusBadgeClass() }}">{{ $stock->statusLabel() }}</span>
                        <span class="badge ui-badge-soft-warning ms-1">Stok {{ number_format((float) $stock->quantity, 3, ',', '.') }} kg</span>
                    </div>
                @endif
            </div>
            <div class="ui-topbar-chip">
                <i class="bi bi-shop"></i>
                <span>{{ $outlet->name }}</span>
            </div>
        </div>
    </div>

    <div class="card ui-section-card">
        <div class="card-body p-4 p-lg-5">
            <form method="POST" action="{{ route('stocks.adjust.store', $product) }}" data-submit-loading>
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div class="ui-form-section mb-0">
                    <div class="ui-form-section-heading">
                        <div class="ui-form-section-icon">
                            <i class="bi bi-arrow-left-right"></i>
                        </div>
                        <div>
                            <h2>Detail Penyesuaian</h2>
                            <p>Perubahan stok akan dicatat otomatis ke riwayat stok.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Toko</label>
                            <input type="text" class="form-control" value="{{ $outlet->name }}" disabled>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="type">Jenis perubahan</label>
                            <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="">Pilih Jenis Perubahan</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected(old('type') === $type)>{{ $typeOptions[$type]['label'] ?? str_replace('_', ' ', ucfirst($type)) }}</option>
                                @endforeach
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="quantity">Jumlah</label>
                            <input type="number" step="0.001" min="0.001" name="quantity" id="quantity" class="form-control @error('quantity') is-invalid @enderror" value="{{ old('quantity') }}" required>
                            @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="reason">Alasan</label>
                            <input type="text" name="reason" id="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" required>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Catatan</label>
                            <textarea name="notes" id="notes" rows="4" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary ui-action-icon-btn">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>
                    <button type="submit" class="btn btn-primary ui-action-icon-btn" data-submit-loading-button>
                        <i class="bi bi-check2-circle"></i>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
