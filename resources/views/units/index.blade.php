<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Satuan',
            'subtitle' => 'Kelola satuan yang dipakai pada produk.',
            'icon' => 'bi-rulers',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('units.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Satuan</a>
    </div>

    <div class="card border-0 shadow-sm">
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
                            <td><span class="badge {{ $unit->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('units.edit', $unit) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('Hapus satuan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada satuan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $units->links() }}
        </div>
    </div>
</x-app-layout>
