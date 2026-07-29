<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Kategori',
            'subtitle' => 'Kelola kategori produk buah.',
            'icon' => 'bi-tags',
        ])
    </x-slot>

    @include('catalog.partials.nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div class="text-muted small">
            Gunakan kategori untuk mengelompokkan produk dengan lebih rapi.
        </div>
        <a href="{{ route('categories.create') }}" class="btn btn-primary ui-action-icon-btn">
            <i class="bi bi-plus-circle"></i>
            Tambah Kategori
        </a>
    </div>

    <div class="card ui-section-card">
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
                            <td>
                                <span class="badge {{ $category->is_active ? 'ui-badge-status-active' : 'ui-badge-status-inactive' }}">
                                    {{ $category->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="ui-action-stack">
                                    <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm btn-outline-primary ui-action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('categories.destroy', $category) }}" class="delete-form">
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
                            <td colspan="5">
                                <div class="ui-empty-state ui-empty-state-panel my-2">
                                    <i class="bi bi-tags"></i>
                                    <div class="fw-semibold">Belum ada kategori</div>
                                    <div class="text-muted small">Tambahkan kategori pertama untuk mulai mengelola produk.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            {{ $categories->links() }}
        </div>
    </div>
</x-app-layout>
