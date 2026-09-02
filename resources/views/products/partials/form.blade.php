@php
    $product = $product ?? null;
    $isEditing = ! empty($product);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" data-submit-loading>
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <div class="ui-form-section">
        <div class="ui-form-section-heading">
            <div class="ui-form-section-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <h2>Informasi Produk</h2>
                <p>Lengkapi data barang, harga, dan gambar agar katalog lebih mudah dipahami.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="code">Kode Barang</label>
                <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $product->code ?? '') }}" placeholder="{{ $isEditing ? '' : 'Otomatis saat disimpan' }}" readonly>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">
                    {{ $isEditing ? 'Kode barang bersifat tetap agar histori transaksi tetap konsisten.' : 'Kode barang dibuat otomatis berdasarkan kategori setelah produk disimpan.' }}
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="name">Nama Barang</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product->name ?? '') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="category_id">Kategori</label>
                <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="unit_id">Satuan</label>
                <select name="unit_id" id="unit_id" class="form-select @error('unit_id') is-invalid @enderror" required>
                    <option value="">Pilih satuan</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('unit_id', $product->unit_id ?? '') == $unit->id)>
                            {{ $unit->name }} ({{ $unit->symbol }})
                        </option>
                    @endforeach
                </select>
                @error('unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="purchase_price">Harga Beli</label>
                <input type="text" inputmode="numeric" min="0" step="0.01" name="purchase_price" id="purchase_price" class="form-control rupiah-input @error('purchase_price') is-invalid @enderror" value="{{ old('purchase_price', $product->purchase_price ?? '') }}" placeholder="Opsional">
                <div class="form-text">Kosongkan jika HPP belum tersedia.</div>
                @error('purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="selling_price">Harga Jual</label>
                <input type="text" inputmode="numeric" min="0" step="0.01" name="selling_price" id="selling_price" class="form-control rupiah-input @error('selling_price') is-invalid @enderror" value="{{ old('selling_price', $product->selling_price ?? '') }}" required>
                @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Deskripsi</label>
                <textarea name="description" id="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description ?? '') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="image">Gambar</label>
                <input type="file" name="image" id="image" class="form-control @error('image') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if (!empty($product?->image_url))
                    <div class="mt-2">
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="ui-product-preview rounded-3 border">
                    </div>
                @endif
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Status aktif</label>
                </div>
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
            {{ $buttonLabel }}
        </button>
    </div>
</form>
