@extends('adminlte::page')

@section('title', 'Create User Account')

@section('content_header')
    <h1>Create User Account</h1>
@stop

@section('content')
    <x-adminlte-card title="User Account Details" theme="dark" icon="fas fa-user-plus">
        <form action="{{ route('admin.user-accounts.store') }}" method="post">
            @include('admin.user-accounts._form')
        </form>
    </x-adminlte-card>
@stop
