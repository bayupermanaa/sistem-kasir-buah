@csrf
<div class="row g-3">
    <div class="col-md-8"><label class="form-label" for="name">Nama supplier</label><input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $supplier->name) }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="phone">Nomor telepon</label><input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $supplier->phone) }}">@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12"><label class="form-label" for="address">Alamat</label><textarea id="address" name="address" class="form-control">{{ old('address', $supplier->address) }}</textarea></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $supplier->is_active))><label class="form-check-label" for="is_active">Supplier aktif</label></div></div>
</div>
<div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button></div>
