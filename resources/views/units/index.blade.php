<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Satuan',
            'subtitle' => 'Kelola satuan yang dipakai pada produk.',
            'icon' => 'bi-rulers',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div class="text-muted small">
            Satuan dipakai untuk memperjelas ukuran jual produk di seluruh katalog.
        </div>
        <a href="{{ route('units.create') }}" class="btn btn-primary ui-action-icon-btn">
            <i class="bi bi-plus-circle"></i>
            Tambah Satuan
        </a>
    </div>

    <div class="card ui-section-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama</th>
                        <th>Simbol</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($units as $unit)
                        <tr>
                            <td class="fw-semibold">{{ $unit->name }}</td>
                            <td>{{ $unit->symbol }}</td>
                            <td>
                                <span class="badge {{ $unit->is_active ? 'ui-badge-status-active' : 'ui-badge-status-inactive' }}">
                                    {{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="ui-action-stack">
                                    <a href="{{ route('units.edit', $unit) }}" class="btn btn-sm btn-outline-primary ui-action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('units.destroy', $unit) }}" class="delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger ui-action-icon-btn" type="submit" data-bs-toggle="tooltip" title="Hapus" aria-label="Hapus">
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="ui-empty-state ui-empty-state-panel my-2">
                                    <i class="bi bi-rulers"></i>
                                    <div class="fw-semibold">Belum ada satuan</div>
                                    <div class="text-muted small">Tambahkan satuan pertama agar produk lebih mudah dikelola.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            {{ $units->links() }}
        </div>
    </div>
</x-app-layout>
