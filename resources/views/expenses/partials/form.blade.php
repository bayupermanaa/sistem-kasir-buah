@php
    $expense = $expense ?? null;
@endphp

<form method="POST" action="{{ $action }}" data-submit-loading>
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <div class="ui-form-section">
        <div class="ui-form-section-heading">
            <div class="ui-form-section-icon">
                <i class="bi bi-wallet2"></i>
            </div>
            <div>
                <h2>Informasi Pengeluaran</h2>
                <p>Data outlet dan pencatat diatur otomatis mengikuti pengguna yang login.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="expense_date">Tanggal</label>
                <input type="date" name="expense_date" id="expense_date" class="form-control @error('expense_date') is-invalid @enderror" value="{{ old('expense_date', optional($expense?->expense_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="category">Kategori</label>
                <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(old('category', $expense->category ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label" for="description">Keterangan</label>
                <input type="text" name="description" id="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description', $expense->description ?? '') }}" placeholder="Misalnya: Belanja plastik dan label" required>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="amount">Nominal</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" min="0.01" step="0.01" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $expense->amount ?? '') }}" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="col-12">
                <label class="form-label" for="notes">Catatan</label>
                <textarea name="notes" id="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" placeholder="Opsional">{{ old('notes', $expense->notes ?? '') }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
        <div class="text-muted small">
            Outlet dan user pencatat akan tersimpan otomatis.
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary ui-action-icon-btn">
                <i class="bi bi-arrow-left"></i>
                Batal
            </a>
            <button type="submit" class="btn btn-primary ui-action-icon-btn" data-submit-loading-button>
                <i class="bi bi-check2-circle"></i>
                {{ $buttonLabel }}
            </button>
        </div>
    </div>
</form>
