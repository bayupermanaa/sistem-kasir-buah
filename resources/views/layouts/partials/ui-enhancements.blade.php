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

            const dateElement = document.querySelector('[data-topbar-date]');
            const timeElement = document.querySelector('[data-topbar-time]');

            const pad = function (value) {
                return String(value).padStart(2, '0');
            };

            const renderClock = function () {
                const now = new Date();

                if (dateElement) {
                    dateElement.textContent = now.toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                    });
                }

                if (timeElement) {
                    timeElement.textContent = [
                        pad(now.getHours()),
                        pad(now.getMinutes()),
                        pad(now.getSeconds()),
                    ].join(':');
                }
            };

            renderClock();
            window.setInterval(renderClock, 1000);

            document.querySelectorAll('form[data-submit-loading]').forEach(function (form) {
                if (form.dataset.loadingReady === 'true') {
                    return;
                }

                form.dataset.loadingReady = 'true';

                form.addEventListener('submit', function () {
                    const submitButton = form.querySelector('[data-submit-loading-button], [type="submit"]');

                    if (! submitButton || submitButton.dataset.loadingApplied === 'true') {
                        return;
                    }

                    submitButton.dataset.loadingApplied = 'true';
                    submitButton.dataset.originalHtml = submitButton.innerHTML;
                    submitButton.disabled = true;
                    submitButton.innerHTML = `
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Memproses...
                    `;
                });
            });
        });
    </script>
    <script>
        (function () {
            if (window.__rupiahInputsInitialized) return;
            window.__rupiahInputsInitialized = true;
            const format = function (value) {
                const digits = String(value || '').replace(/\D/g, '');
                return digits ? new Intl.NumberFormat('id-ID').format(Number(digits)) : '';
            };
            document.querySelectorAll(".rupiah-input").forEach(function (input) {
                input.value = format(input.value);
                input.addEventListener("input", function () { input.value = format(input.value); });
                const form = input.form;
                if (form && form.dataset.rupiahReady !== "true") {
                    form.dataset.rupiahReady = "true";
                    form.addEventListener("submit", function () {
                        form.querySelectorAll(".rupiah-input").forEach(function (field) { field.value = field.value.replace(/\D/g, ""); });
                    });
                }
            });
        }());
    </script>
@endif
