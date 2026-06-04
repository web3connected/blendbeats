<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>BlendBeats Admin Login</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/css/adminlte.min.css">
        <style>
            :root {
                color-scheme: dark;
            }

            body.login-page {
                min-height: 100vh;
                background:
                    radial-gradient(circle at 15% 20%, rgba(239, 68, 68, 0.18), transparent 34%),
                    radial-gradient(circle at 82% 12%, rgba(20, 184, 166, 0.12), transparent 30%),
                    #08090d;
                color: #f8fafc;
            }

            .login-box {
                width: min(420px, calc(100vw - 32px));
            }

            .login-logo a {
                color: #fff;
                font-weight: 800;
                letter-spacing: 0;
            }

            .card {
                border: 1px solid rgba(148, 163, 184, 0.2);
                border-radius: 8px;
                background: #111827;
                box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
            }

            .card-header {
                border-bottom-color: rgba(148, 163, 184, 0.18);
            }

            .form-control,
            .input-group-text {
                border-color: #334155;
                background: #020617;
                color: #f8fafc;
            }

            .form-control:focus {
                border-color: #ef4444;
                background: #020617;
                color: #fff;
                box-shadow: 0 0 0 .2rem rgba(239, 68, 68, .18);
            }

            .input-group-text {
                min-width: 44px;
                justify-content: center;
            }

            .btn-primary {
                border-color: #ef4444;
                background: #ef4444;
                font-weight: 700;
            }

            .btn-primary:hover,
            .btn-primary:focus {
                border-color: #dc2626;
                background: #dc2626;
            }

            .text-muted,
            .login-box-msg {
                color: #cbd5e1 !important;
            }

            .alert {
                border-radius: 6px;
            }
        </style>
    </head>
    <body class="login-page dark-mode">
        <div class="login-box">
            <div class="login-logo">
                <a href="{{ route('login') }}">BlendBeats Admin</a>
            </div>

            <div class="card card-outline card-danger">
                <div class="card-header text-center">
                    <p class="mb-0 text-muted">Secure admin access</p>
                </div>
                <div class="card-body login-card-body">
                    <p class="login-box-msg">Sign in to manage BlendBeats</p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form action="{{ route('admin.login.store') }}" method="post">
                        @csrf
                        <div class="input-group mb-3">
                            <input
                                type="email"
                                name="email"
                                class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email') }}"
                                placeholder="Email"
                                autocomplete="email"
                                autofocus
                                required
                            >
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-envelope" aria-hidden="true"></span>
                                </div>
                            </div>
                        </div>

                        <div class="input-group mb-3">
                            <input
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Password"
                                autocomplete="current-password"
                                required
                            >
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-lock" aria-hidden="true"></span>
                                </div>
                            </div>
                        </div>

                        <div class="row align-items-center">
                            <div class="col-7">
                                <div class="icheck-primary">
                                    <input type="checkbox" id="remember" name="remember" value="1">
                                    <label for="remember" class="text-muted">Remember me</label>
                                </div>
                            </div>
                            <div class="col-5">
                                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/js/adminlte.min.js"></script>
    </body>
</html>
