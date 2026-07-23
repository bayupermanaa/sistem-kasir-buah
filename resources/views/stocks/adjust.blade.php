<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Penyesuaian Stok',
            'subtitle' => 'Tambah atau kurangi stok untuk satu produk dan outlet tertentu.',
            'icon' => 'bi-arrow-left-right',
        ])
    </x-slot>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="mb-4">
                <div class="fw-semibold">{{ $product->name }}</div>
                <div class="text-muted small">{{ $product->code }} · {{ $product->category?->name }} · {{ $product->unit?->name }}</div>
            </div>

            <form method="POST" action="{{ route('stocks.adjust.store', $product) }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="outlet_id">Outlet</label>
                        @if (auth()->user()->role === 'cashier')
                            <input type="hidden" name="outlet_id" value="{{ auth()->user()->outlet_id }}">
                            <input type="text" class="form-control" value="{{ auth()->user()->outlet?->name }}" disabled>
                        @else
                            <select name="outlet_id" id="outlet_id" class="form-select @error('outlet_id') is-invalid @enderror" required>
                                <option value="">Pilih outlet</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}" @selected(old('outlet_id', $selectedOutletId) == $outlet->id)>{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                            @error('outlet_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="type">Jenis perubahan</label>
                        <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="">Pilih jenis</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>
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

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Penyesuaian</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
