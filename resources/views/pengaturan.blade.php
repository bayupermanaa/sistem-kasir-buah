<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Halaman Pengaturan',
            'subtitle' => 'Kelola pengaturan dasar sistem dan akun kasir.',
            'icon' => 'bi-gear',
        ])
    </x-slot>

    @php
        $activeCashiersCount = $cashiers->where('is_active', true)->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Total Cashier</span>
                        <span class="ui-kpi-icon"><i class="bi bi-people"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format($cashiers->count(), 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Akun kasir terdaftar dalam sistem</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Cashier Aktif</span>
                        <span class="ui-kpi-icon"><i class="bi bi-check-circle"></i></span>
                    </div>
                    <div class="ui-summary-value">{{ number_format($activeCashiersCount, 0, ',', '.') }}</div>
                    <div class="ui-summary-note">Akun yang dapat digunakan login</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card ui-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ui-summary-label">Akses</span>
                        <span class="ui-kpi-icon"><i class="bi bi-shield-lock"></i></span>
                    </div>
                    <div class="ui-summary-value fs-5">Owner Only</div>
                    <div class="ui-summary-note">Cashier tidak dapat membuka halaman ini</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="ui-form-section h-100">
                <div class="ui-form-section-heading">
                    <div class="ui-form-section-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div>
                        <h2>Manajemen Akun Kasir</h2>
                        <p>Owner dapat melihat akun kasir yang aktif maupun nonaktif dan mengganti password jika diperlukan.</p>
                    </div>
                </div>

                @if ($cashiers->isEmpty())
                    <div class="ui-empty-state ui-empty-state-panel">
                        <i class="bi bi-people"></i>
                        <div class="fw-semibold">Belum ada akun kasir</div>
                        <div class="text-muted small">Akun kasir dapat dibuat dari pengaturan sistem jika dibutuhkan.</div>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Toko</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cashiers as $cashier)
                                    <tr>
                                        <td class="fw-semibold">{{ $cashier->name }}</td>
                                        <td>{{ $cashier->username }}</td>
                                        <td>
                                            <span class="badge ui-role-badge-cashier text-capitalize">
                                                {{ $cashier->role }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $cashier->is_active ? 'ui-badge-status-active' : 'ui-badge-status-inactive' }}">
                                                {{ $cashier->is_active ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                        <td>{{ $cashier->outlet?->name ?? '-' }}</td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary ui-action-icon-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#cashierPasswordModal-{{ $cashier->id }}"
                                            >
                                                <i class="bi bi-key"></i>
                                                Ubah Password
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="ui-form-section h-100">
                <div class="ui-form-section-heading">
                    <div class="ui-form-section-icon">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div>
                        <h2>Informasi Sistem</h2>
                        <p>Ringkasan singkat konfigurasi yang sedang digunakan.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6 col-xl-12">
                        <div class="ui-surface p-3 h-100">
                            <div class="text-muted small mb-1">Nama Toko</div>
                            <div class="fw-semibold">Toko Buah Teras Fatimah</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-12">
                        <div class="ui-surface p-3 h-100">
                            <div class="text-muted small mb-1">Login Aktif</div>
                            <div class="fw-semibold">{{ auth()->user()->name ?? '-' }}</div>
                            <div class="text-muted small mt-2">Role: <span class="text-capitalize">{{ auth()->user()->role ?? '-' }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($cashiers as $cashier)
        <div class="modal fade" id="cashierPasswordModal-{{ $cashier->id }}" tabindex="-1" aria-labelledby="cashierPasswordModalLabel-{{ $cashier->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content ui-surface border-0">
                    <form method="POST" action="{{ route('pengaturan.cashiers.password.update', $cashier) }}" data-submit-loading>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="cashier_id" value="{{ $cashier->id }}">

                        <div class="modal-header border-0 pb-0">
                            <div>
                                <h1 class="modal-title fs-5" id="cashierPasswordModalLabel-{{ $cashier->id }}">Ubah Password Cashier</h1>
                                <div class="text-muted small">Perbarui password untuk {{ $cashier->name }} ({{ $cashier->username }}).</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>

                        <div class="modal-body">
                            <div class="alert alert-info border-0 mb-3">
                                Password baru tidak akan ditampilkan kembali setelah disimpan.
                            </div>

                            <div class="mb-3">
                                <label for="password-{{ $cashier->id }}" class="form-label fw-semibold">Password Baru</label>
                                <input
                                    type="password"
                                    id="password-{{ $cashier->id }}"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Minimal 8 karakter"
                                >
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-0">
                                <label for="password_confirmation-{{ $cashier->id }}" class="form-label fw-semibold">Konfirmasi Password</label>
                                <input
                                    type="password"
                                    id="password_confirmation-{{ $cashier->id }}"
                                    name="password_confirmation"
                                    class="form-control @error('password_confirmation') is-invalid @enderror"
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Ulangi password baru"
                                >
                                @error('password_confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary ui-action-icon-btn" data-submit-loading-button>
                                <i class="bi bi-key"></i>
                                Simpan Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    @if (old('cashier_id'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('cashierPasswordModal-{{ old('cashier_id') }}');

                if (modalElement) {
                    new bootstrap.Modal(modalElement).show();
                }
            });
        </script>
    @endif
</x-app-layout>
