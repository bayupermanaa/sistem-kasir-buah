@if (! isset($uiEnhancementsLoaded))
    @php($uiEnhancementsLoaded = true)

    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: @json(session('success')),
                        confirmButtonText: 'OK',
                    });
                }
            });
        </script>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.__uiEnhancementsInitialized) {
                return;
            }

            window.__uiEnhancementsInitialized = true;

            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
                new bootstrap.Tooltip(element);
            });

            document.querySelectorAll('.js-action-edit').forEach(function (element) {
                if (element.dataset.uiEnhanced === 'true') {
                    return;
                }

                const icon = document.createElement('i');
                icon.className = 'bi bi-pencil-square me-1';
                element.prepend(icon);
                element.dataset.uiEnhanced = 'true';
            });

            document.querySelectorAll('.js-action-delete').forEach(function (element) {
                if (element.dataset.uiEnhanced === 'true') {
                    return;
                }

                const icon = document.createElement('i');
                icon.className = 'bi bi-trash me-1';
                element.prepend(icon);
                element.dataset.uiEnhanced = 'true';
            });

            document.addEventListener('submit', function (event) {
                const form = event.target.closest('form.delete-form');
                if (! form || form.dataset.confirmed === 'true') {
                    return;
                }

                event.preventDefault();

                Swal.fire({
                    icon: 'warning',
                    title: 'Hapus Data?',
                    text: 'Data yang dihapus tidak dapat dikembalikan.',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.dataset.confirmed = 'true';
                        form.submit();
                    }
                });
            });
        });
    </script>
@endif
