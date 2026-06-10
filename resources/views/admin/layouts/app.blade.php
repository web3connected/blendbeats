@extends('adminlte::page')

@section('title', trim(config('adminlte.title_prefix').' '.($title ?? 'Admin').' '.config('adminlte.title_postfix')))

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h1 class="m-0">{{ $heading ?? $title ?? 'Admin' }}</h1>
            @isset($subtitle)
                <p class="text-muted mb-0">{{ $subtitle }}</p>
            @endisset
        </div>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sign-out-alt mr-1"></i> Sign out
            </button>
        </form>
    </div>
@stop

@section('content')
    @yield('admin_content')
@stop

@section('footer')
    <strong>{{ config('app.name') }}</strong>
    <span class="text-muted ml-1">Administration</span>
@stop
