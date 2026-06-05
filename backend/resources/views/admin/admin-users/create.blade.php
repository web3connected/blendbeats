@extends('adminlte::page')

@section('title', 'Create Admin User')

@section('content_header')
    <h1>Create Admin User</h1>
@stop

@section('content')
    <x-adminlte-card title="Admin User Details" theme="dark" icon="fas fa-user-plus">
        <form action="{{ route('admin.admin-users.store') }}" method="post">
            @include('admin.admin-users._form')
        </form>
    </x-adminlte-card>
@stop
