@extends('adminlte::page')

@section('title', 'Edit Role')

@section('content_header')
    <h1>Edit Role</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-adminlte-card title="Role Details" theme="dark" icon="fas fa-user-lock">
        <form action="{{ route('admin.role-manager.update', $role) }}" method="post">
            @csrf
            @method('PUT')
            @include('admin.role-manager._form')
        </form>
    </x-adminlte-card>
@stop
