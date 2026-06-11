@extends('adminlte::page')

@section('title', trim(config('adminlte.title_prefix').' '.($title ?? 'Admin').' '.config('adminlte.title_postfix')))

@section('content_header')
    <h1 class="m-0">{{ $heading ?? $title ?? 'Admin' }}</h1>
    @isset($subtitle)
        <p class="text-muted mb-0">{{ $subtitle }}</p>
    @endisset
@stop

@section('content')
    @yield('admin_content')
@stop

@section('footer')
    <strong>{{ config('app.name') }}</strong>
    <span class="text-muted ml-1">Administration</span>
@stop
