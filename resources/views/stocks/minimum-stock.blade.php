<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Minimum Stok',
            'subtitle' => 'Atur batas minimum stok untuk peringatan stok menipis.',
            'icon' => 'bi-exclamation-triangle',
        ])
    </x-slot>

    <div class="ui-surface ui-banner p-3 p-lg-4 mb-3">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <div class="text-muted small mb-1">Produk</div>
                <div class="fw-bold fs-5">{{ $stock->product?->name }}</div>
                <div class="text-muted small">{{ $stock->outlet?->name }} &middot; Saat ini {{ number_format((float) $stock->quantity, 3, ',', '.') }}</div>
            </div>
            <div class="ui-topbar-chip">
                <i class="bi bi-exclamation-triangle"></i>
                <span>Peringatan stok</span>
            </div>
        </div>
    </div>

    <div class="card ui-section-card">
        <div class="card-body p-4 p-lg-5">
            <form method="POST" action="{{ route('stocks.minimum.update', $stock) }}" data-submit-loading>
                @csrf
                @method('PUT')

                <div class="ui-form-section mb-0">
                    <div class="ui-form-section-heading">
                        <div class="ui-form-section-icon">
                            <i class="bi bi-bell"></i>
                        </div>
                        <div>
                            <h2>Minimum Stok</h2>
                            <p>Atur batas minimum agar stok menipis mudah terdeteksi.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="minimum_stock">Minimum Stok</label>
                            <input type="number" step="0.001" min="0" name="minimum_stock" id="minimum_stock" class="form-control @error('minimum_stock') is-invalid @enderror" value="{{ old('minimum_stock', $stock->minimum_stock) }}" required>
                            @error('minimum_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary ui-action-icon-btn">
                        <i class="bi bi-arrow-left"></i>
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary ui-action-icon-btn" data-submit-loading-button>
                        <i class="bi bi-check2-circle"></i>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
