@php
    $category = $category ?? null;
@endphp

<form method="POST" action="{{ $action }}" data-submit-loading>
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <div class="ui-form-section">
        <div class="ui-form-section-heading">
            <div class="ui-form-section-icon">
                <i class="bi bi-tag"></i>
            </div>
            <div>
                <h2>Informasi Kategori</h2>
                <p>Isi nama dan deskripsi kategori dengan jelas agar mudah dipilih saat input produk.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="name">Nama Kategori</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $category->name ?? '') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Deskripsi</label>
                <textarea name="description" id="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $category->description ?? '') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Status aktif</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary ui-action-icon-btn">
            <i class="bi bi-arrow-left"></i>
            Batal
        </a>
        <button type="submit" class="btn btn-primary ui-action-icon-btn" data-submit-loading-button>
            <i class="bi bi-check2-circle"></i>
            {{ $buttonLabel }}
        </button>
    </div>
</form>
