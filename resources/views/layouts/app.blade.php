<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        @vite(['resources/css/app.css'])
    </head>
    <body class="ui-app-shell bg-body-tertiary">
        <div class="d-lg-none">
            <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
                <div class="offcanvas-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem;">
                            <i class="bi bi-shop"></i>
                        </div>
                        <div>
                            <div id="mobileSidebarLabel" class="fw-semibold">Sistem Kasir</div>
                            <small class="text-muted">Toko Buah Teras Fatimah</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body p-0">
                    @include('layouts.partials.sidebar', ['mobile' => true])
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div class="row min-vh-100 g-0">
                <div class="col-lg-2 d-none d-lg-block">
                    @include('layouts.partials.sidebar', ['mobile' => false])
                </div>

                <div class="col-12 col-lg-10 d-flex flex-column">
                    @include('layouts.partials.topbar')

                    <main class="flex-grow-1 p-3 p-lg-4">
                        @include('layouts.partials.flash-message')

                        @if (isset($header))
                            <div class="mb-3">
                                {{ $header }}
                            </div>
                        @endif

                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        @stack('scripts')
        @include('layouts.partials.ui-enhancements')
    </body>
</html>

