@extends('adminlte::page')

@section('title', 'Create Role')

@section('content_header')
    <h1>Create Role</h1>
@stop

@section('content')
    <x-adminlte-card title="Role Details" theme="dark" icon="fas fa-user-lock">
        <form action="{{ route('admin.role-manager.store') }}" method="post">
            @include('admin.role-manager._form')
        </form>
    </x-adminlte-card>
@stop
