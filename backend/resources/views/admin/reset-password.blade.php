@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Set Admin Password')
@section('auth_header', 'Set admin password')

@section('auth_body')
    <p class="login-box-msg">Choose a new password for your admin account.</p>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.password.update') }}" method="post">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="input-group mb-3">
            <input
                type="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', $email) }}"
                placeholder="Email"
                autocomplete="email"
                required
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
                placeholder="New password"
                autocomplete="new-password"
                required
            >
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>
        </div>

        <div class="input-group mb-3">
            <input
                type="password"
                name="password_confirmation"
                class="form-control"
                placeholder="Confirm new password"
                autocomplete="new-password"
                required
            >
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-danger btn-block">
            <span class="fas fa-sync-alt"></span>
            Set Password
        </button>
    </form>
@stop

@section('auth_footer')
    <p class="my-0">
        <a href="{{ route('admin.login') }}">Back to login</a>
    </p>
@stop

@section('css')
    <style>
        .login-page {
            background:
                radial-gradient(circle at 15% 20%, rgba(220, 53, 69, .18), transparent 34%),
                radial-gradient(circle at 82% 12%, rgba(23, 162, 184, .12), transparent 30%),
                #08090d;
        }

        .login-logo a {
            color: #fff;
            font-weight: 800;
            text-decoration: none;
        }

        .login-card-body,
        .card-header,
        .card-footer {
            background: #111827 !important;
            color: #e5e7eb;
        }

        .form-control,
        .input-group-text {
            border-color: #334155;
            background: #020617;
            color: #f8fafc;
        }
    </style>
@stop
