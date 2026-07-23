<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login | {{ config('app.name', 'Sistem Kasir') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
    <div class="min-vh-100 d-flex align-items-center py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle shadow-sm mb-3" style="width: 4rem; height: 4rem;">
                            <i class="bi bi-shop fs-3"></i>
                        </div>
                        <h1 class="h4 mb-1">Sistem Kasir</h1>
                        <div class="text-muted">Toko Buah Teras Fatimah</div>
                    </div>

                    <div class="card border-0 shadow-lg">
                        <div class="card-body p-4 p-md-5">
                            <div class="mb-4">
                                <h2 class="h5 mb-1">Masuk ke akun Anda</h2>
                                <p class="text-muted mb-0">Gunakan username dan password untuk melanjutkan.</p>
                            </div>

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <div class="fw-semibold mb-1">Terjadi kesalahan:</div>
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (session('status'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('status') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('login') }}" id="loginForm" novalidate>
                                @csrf

                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input
                                        id="username"
                                        type="text"
                                        name="username"
                                        value="{{ old('username') }}"
                                        class="form-control @error('username') is-invalid @enderror"
                                        required
                                        autofocus
                                        autocomplete="username"
                                    >
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <input
                                            id="password"
                                            type="password"
                                            name="password"
                                            class="form-control @error('password') is-invalid @enderror"
                                            required
                                            autocomplete="current-password"
                                        >
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Tampilkan password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @error('password')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="remember_me" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="remember_me">Ingat saya</label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100" id="loginButton">
                                    <span class="button-label">Login</span>
                                    <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const buttonLabel = loginButton.querySelector('.button-label');
        const spinner = loginButton.querySelector('.spinner-border');
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword?.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            togglePassword.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            togglePassword.setAttribute('aria-label', isPassword ? 'Sembunyikan password' : 'Tampilkan password');
        });

        loginForm?.addEventListener('submit', () => {
            loginButton.disabled = true;
            buttonLabel.textContent = 'Memproses...';
            spinner.classList.remove('d-none');
        });
    </script>
</body>
</html>
