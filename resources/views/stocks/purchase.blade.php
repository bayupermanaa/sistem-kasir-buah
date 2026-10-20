<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', ['title' => 'Pembelian Stok', 'subtitle' => 'Tambah stok sekaligus catat arus kas pembelian.', 'icon' => 'bi-cart-plus'])
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-8">
            <div class="ui-surface ui-section-card p-3 p-lg-4">
                <div class="ui-banner p-3 mb-4">
                    <div class="fw-semibold">{{ $product->name }}</div>
                    <div class="text-muted small">{{ $product->code }} · Stok saat ini: {{ number_format((float) ($stock?->quantity ?? 0), 3, ',', '.') }} kg</div>
                </div>
                <form method="POST" action="{{ route('stocks.purchase.store', $product) }}" data-submit-loading>
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="quantity">Jumlah stok (kg)</label>
                            <input type="number" step="0.001" min="0.001" name="quantity" id="quantity" class="form-control @error('quantity') is-invalid @enderror" value="{{ old('quantity') }}" required>
                            @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="unit_cost">Harga beli per kg (opsional)</label>
                            <input type="text" inputmode="numeric" name="unit_cost" id="unit_cost" class="form-control rupiah-input @error('unit_cost') is-invalid @enderror" value="{{ old('unit_cost') }}" placeholder="Contoh: 25.000">
                            @error('unit_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="purchase_date">Tanggal pembelian</label>
                            <input type="date" name="purchase_date" id="purchase_date" class="form-control @error('purchase_date') is-invalid @enderror" value="{{ old('purchase_date', now()->format('Y-m-d')) }}" required>
                            @error('purchase_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="payment_method">Metode pembayaran</label>
                            <select name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                                <option value="">Tidak dicatat</option>
                                <option value="cash" @selected(old('payment_method') === 'cash')>Tunai</option>
                                <option value="qris" @selected(old('payment_method') === 'qris')>QRIS</option>
                                <option value="transfer" @selected(old('payment_method') === 'transfer')>Transfer</option>
                                <option value="credit" @selected(old('payment_method') === 'credit')>Kredit / Utang Supplier</option>
                            </select>
                            @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 credit-fields d-none">
                            <label class="form-label" for="supplier_id">Supplier</label>
                            <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                                <option value="">Pilih supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 credit-fields d-none">
                            <label class="form-label" for="invoice_number">Nomor faktur <span class="text-muted">(opsional)</span></label>
                            <input type="text" name="invoice_number" id="invoice_number" class="form-control @error('invoice_number') is-invalid @enderror" value="{{ old('invoice_number') }}">
                            @error('invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 credit-fields d-none">
                            <label class="form-label" for="due_date">Jatuh tempo</label>
                            <input type="date" name="due_date" id="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date') }}">
                            @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Catatan</label>
                            <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="alert alert-info mt-4 mb-0" id="purchaseHelp">Pembelian stok menambah persediaan dan dicatat sebagai kas keluar pembelian stok, bukan Pengeluaran operasional.</div>
                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary ui-action-icon-btn"><i class="bi bi-arrow-left"></i>Kembali</a>
                        <button type="submit" class="btn btn-primary ui-action-icon-btn" data-submit-loading-button><i class="bi bi-check2-circle"></i>Simpan Pembelian</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const method = document.getElementById('payment_method');
                const fields = document.querySelectorAll('.credit-fields');
                const help = document.getElementById('purchaseHelp');
                const toggle = () => {
                    const credit = method.value === 'credit';
                    fields.forEach((field) => field.classList.toggle('d-none', !credit));
                    help.textContent = credit
                        ? 'Pembelian kredit menambah stok dan utang supplier tanpa mengurangi kas.'
                        : 'Pembelian stok menambah persediaan dan dicatat sebagai kas keluar pembelian stok, bukan Pengeluaran operasional.';
                };
                method.addEventListener('change', toggle);
                toggle();
            });
        </script>
    @endpush
</x-app-layout>
