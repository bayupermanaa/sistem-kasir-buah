<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Saldo Awal Akuntansi',
            'subtitle' => 'Catat posisi pembukaan setelah tanggal cutover yang disetujui.',
            'icon' => 'bi-journal-check',
        ])
    </x-slot>

    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-4">
        <a href="{{ route('pengaturan') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Pengaturan
        </a>
        <span class="badge text-bg-primary"><i class="bi bi-shop me-1"></i>{{ $outlet->name }}</span>
    </div>

    @if ($openingEntry)
        <div class="alert alert-success border-0 shadow-sm">
            <i class="bi bi-check-circle me-2"></i>
            Saldo awal sudah diposting pada {{ $openingEntry->journal_date?->translatedFormat('d/m/Y') }} dan tidak dapat diedit langsung.
            Koreksi harus menggunakan mekanisme jurnal koreksi yang aman.
        </div>
    @else
        <div class="alert alert-info border-0 shadow-sm">
            <i class="bi bi-info-circle me-2"></i>
            Isi hanya saldo aktual pada tanggal mulai pembukuan. Sistem tidak membuat saldo historis atau angka dummy.
        </div>

        <form method="POST" action="{{ route('accounting.opening-balance.store') }}" data-submit-loading>
            @csrf
            <div class="card ui-table-card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cutover_date" class="form-label fw-semibold">Tanggal mulai pembukuan</label>
                            <input type="date" id="cutover_date" name="cutover_date" value="{{ old('cutover_date', $cutoverDate?->toDateString()) }}" class="form-control @error('cutover_date') is-invalid @enderror" required>
                            @error('cutover_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <hr class="my-4">
                    <h2 class="h5 mb-3 text-primary">Aset Awal</h2>
                    <div class="row g-3">
                        @foreach ([
                            'cash_balance' => 'Kas tunai awal',
                            'bank_balance' => 'Bank/QRIS awal',
                            'inventory_balance' => 'Nilai persediaan awal',
                            'fixed_asset_balance' => 'Nilai aset tetap awal',
                        ] as $field => $label)
                            <div class="col-md-6">
                                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" min="0" step="0.01" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, '0.00') }}" class="form-control balance-input @error($field) is-invalid @enderror">
                                </div>
                                @error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>

                    <h2 class="h5 mb-3 mt-4 text-primary">Kewajiban dan Ekuitas</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="liabilities_balance" class="form-label">Total utang awal</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" min="0" step="0.01" id="liabilities_balance" name="liabilities_balance" value="{{ old('liabilities_balance', '0.00') }}" class="form-control balance-input @error('liabilities_balance') is-invalid @enderror">
                            </div>
                            @error('liabilities_balance')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="capital_balance" class="form-label">Modal pemilik awal</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" min="0" step="0.01" id="capital_balance" name="capital_balance" value="{{ old('capital_balance', '0.00') }}" class="form-control balance-input @error('capital_balance') is-invalid @enderror">
                            </div>
                            @error('capital_balance')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    @error('balances')<div class="alert alert-danger mt-4 mb-0">{{ $message }}</div>@enderror

                    <div class="row g-3 mt-2">
                        <div class="col-md-4"><div class="ui-surface p-3"><div class="small text-muted">Total Debit</div><div class="fs-4 fw-bold text-primary" id="openingDebit">Rp 0</div></div></div>
                        <div class="col-md-4"><div class="ui-surface p-3"><div class="small text-muted">Total Kredit</div><div class="fs-4 fw-bold text-primary" id="openingCredit">Rp 0</div></div></div>
                        <div class="col-md-4"><div class="ui-surface p-3"><div class="small text-muted">Selisih</div><div class="fs-4 fw-bold" id="openingDifference">Rp 0</div></div></div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('pengaturan') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-journal-check me-1"></i>Posting Saldo Awal</button>
                    </div>
                </div>
            </div>
        </form>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const inputs = Array.from(document.querySelectorAll('.balance-input'));
                const format = value => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(value);
                const update = () => {
                    const values = Object.fromEntries(inputs.map(input => [input.name, Number(input.value || 0)]));
                    const debit = (values.cash_balance || 0) + (values.bank_balance || 0) + (values.inventory_balance || 0) + (values.fixed_asset_balance || 0);
                    const credit = (values.liabilities_balance || 0) + (values.capital_balance || 0);
                    const difference = debit - credit;
                    document.getElementById('openingDebit').textContent = format(debit);
                    document.getElementById('openingCredit').textContent = format(credit);
                    document.getElementById('openingDifference').textContent = format(difference);
                    document.getElementById('openingDifference').className = `fs-4 fw-bold ${difference === 0 ? 'text-success' : 'text-danger'}`;
                };
                inputs.forEach(input => input.addEventListener('input', update));
                update();
            });
        </script>
    @endpush
</x-app-layout>
