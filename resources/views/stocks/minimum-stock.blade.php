<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Minimum Stok',
            'subtitle' => 'Atur batas minimum stok untuk peringatan stok menipis.',
            'icon' => 'bi-exclamation-triangle',
        ])
    </x-slot>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="mb-4">
                <div class="fw-semibold">{{ $stock->product?->name }}</div>
                <div class="text-muted small">{{ $stock->outlet?->name }} · Saat ini {{ number_format((float) $stock->quantity, 3, ',', '.') }}</div>
            </div>

            <form method="POST" action="{{ route('stocks.minimum.update', $stock) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="minimum_stock">Minimum Stok</label>
                        <input type="number" step="0.001" min="0" name="minimum_stock" id="minimum_stock" class="form-control @error('minimum_stock') is-invalid @enderror" value="{{ old('minimum_stock', $stock->minimum_stock) }}" required>
                        @error('minimum_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
