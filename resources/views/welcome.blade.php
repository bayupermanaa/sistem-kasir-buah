<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Sistem Kasir') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="min-vh-100 d-flex align-items-center justify-content-center p-4">
        <section class="ui-surface ui-welcome-card text-center p-4 p-md-5">
            <div class="ui-login-mark d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3">
                <i class="bi bi-shop fs-3"></i>
            </div>
            <h1 class="h3 mb-2">Sistem Kasir</h1>
            <p class="text-muted mb-4">Toko Buah Teras Fatimah</p>
            <a href="{{ route('login') }}" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
            </a>
        </section>
    </main>
</body>
</html>
