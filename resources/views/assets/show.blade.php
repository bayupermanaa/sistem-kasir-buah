<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Detail Aset',
            'subtitle' => 'Rincian pembelian dan status aset.',
            'icon' => 'bi-safe2',
        ])
    </x-slot>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="card ui-table-card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Kode Aset</div>
                            <div class="fw-semibold">{{ $asset->asset_code }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Nama Aset</div>
                            <div class="fw-semibold">{{ $asset->name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Kategori</div>
                            <div>{{ $asset->category }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Tanggal Beli</div>
                            <div>{{ $asset->formatted_purchase_date }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Harga Satuan</div>
                            <div>{{ $asset->formatted_purchase_price }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Jumlah</div>
                            <div>{{ number_format((int) $asset->quantity, 0, ',', '.') }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Total Nilai</div>
                            <div class="fw-semibold">{{ $asset->formatted_total_cost }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Metode Pembayaran</div>
                            <div><span class="badge {{ $asset->payment_method_badge_class }}">{{ $asset->payment_method_label }}</span></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Status</div>
                            <div><span class="badge {{ $asset->status_badge_class }}">{{ $asset->status_label }}</span></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Masa Manfaat</div>
                            <div>{{ $asset->useful_life_years ? $asset->useful_life_years.' tahun' : '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Nilai Residu</div>
                            <div>Rp {{ number_format((float) $asset->residual_value, 0, ',', '.') }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small mb-1">Catatan</div>
                            <div>{{ $asset->notes ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Outlet</div>
                            <div>{{ $asset->outlet?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Dibuat Oleh</div>
                            <div>{{ $asset->creator?->name ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <a href="{{ route('assets.index') }}" class="btn btn-outline-secondary ui-action-icon-btn">
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('assets.edit', $asset) }}" class="btn btn-outline-primary ui-action-icon-btn">
                        <i class="bi bi-pencil-square"></i>
                        Edit
                    </a>
                    <form method="POST" action="{{ route('assets.destroy', $asset) }}" class="delete-form d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger ui-action-icon-btn">
                            <i class="bi bi-trash"></i>
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
