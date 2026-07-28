<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Kategori',
            'subtitle' => 'Kelola kategori produk buah.',
            'icon' => 'bi-tags',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Kategori</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama</th>
                        <th>Slug</th>
                        <th>Deskripsi</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td class="fw-semibold">{{ $category->name }}</td>
                            <td>{{ $category->slug }}</td>
                            <td>{{ $category->description ?: '-' }}</td>
                            <td><span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm btn-outline-primary js-action-edit" data-bs-toggle="tooltip" title="Edit" aria-label="Edit">Edit</a>
                                    <form method="POST" action="{{ route('categories.destroy', $category) }}" class="delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger js-action-delete" type="submit" data-bs-toggle="tooltip" title="Hapus" aria-label="Hapus">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada kategori.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $categories->links() }}
        </div>
    </div>
</x-app-layout>

