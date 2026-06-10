<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} Admin Login</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte.min.css') }}">
    <style>
        body.login-page {
            background: #111827;
            color: #e5e7eb;
        }

        .login-logo a {
            color: #f9fafb;
        }

        .login-card-body {
            background: #1f2937;
            border: 1px solid #374151;
            color: #d1d5db;
        }

        .login-box-msg,
        .icheck-primary label {
            color: #d1d5db;
        }

        .form-control,
        .input-group-text {
            background-color: #111827;
            border-color: #4b5563;
            color: #f9fafb;
        }

        .form-control:focus {
            background-color: #111827;
            border-color: #3b82f6;
            color: #f9fafb;
        }

        .form-control::placeholder {
            color: #9ca3af;
        }
    </style>
</head>
<body class="hold-transition login-page dark-mode">
    <div class="login-box">
        <div class="login-logo">
            <a href="{{ route('admin.login') }}"><b>BlendBeats</b> Admin</a>
        </div>

        <div class="card card-outline card-primary">
            <div class="card-body login-card-body">
                <p class="login-box-msg">Sign in to manage the platform</p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}">
                    @csrf

                    <div class="input-group mb-3">
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror"
                            placeholder="Email"
                            autocomplete="email"
                            required
                            autofocus
                        >
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-envelope"></span>
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
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-8">
                            <div class="icheck-primary">
                                <input type="checkbox" id="remember" name="remember" value="1">
                                <label for="remember">Remember me</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/adminlte/dist/js/adminlte.min.js') }}"></script>
</body>
</html>
