<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', ['title' => 'Supplier', 'subtitle' => 'Kelola daftar supplier toko.', 'icon' => 'bi-truck'])
    </x-slot>
    <div class="d-flex justify-content-end mb-3"><a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Supplier</a></div>
    <div class="card ui-table-card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table align-middle mb-0"><thead><tr><th>Nama</th><th>Telepon</th><th>Status</th><th>Riwayat Utang</th><th class="text-end">Aksi</th></tr></thead><tbody>
        @forelse ($suppliers as $supplier)
            <tr><td class="fw-semibold">{{ $supplier->name }}</td><td>{{ $supplier->phone ?: '-' }}</td><td><span class="badge {{ $supplier->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td>{{ number_format($supplier->payables_count, 0, ',', '.') }}</td><td class="text-end"><div class="d-flex justify-content-end flex-wrap gap-1"><a class="btn btn-sm btn-outline-primary" href="{{ route('suppliers.edit', $supplier) }}" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil-square"></i> Edit</a><form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="delete-form">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Hapus" type="submit"><i class="bi bi-trash"></i> Hapus</button></form></div></td></tr>
        @empty
            <tr><td colspan="5"><div class="ui-empty-state py-5"><i class="bi bi-truck"></i><div class="fw-semibold">Belum ada supplier</div><div class="text-muted small">Tambahkan supplier untuk mencatat utang usaha.</div></div></td></tr>
        @endforelse
        </tbody></table></div></div></div>
    <div class="mt-3">{{ $suppliers->links() }}</div>
</x-app-layout>
