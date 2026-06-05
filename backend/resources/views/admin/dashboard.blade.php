@extends('adminlte::page')

@section('title', 'BlendBeats Admin')
@section('content_header')
    <h1>Dashboard</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-lg-4 col-12">
            <x-adminlte-small-box
                title="Admin"
                text="Control Panel"
                icon="fas fa-user-shield"
                theme="danger"
            />
        </div>
        <div class="col-lg-4 col-12">
            <x-adminlte-small-box
                title="API"
                text="BlendBeats backend"
                icon="fas fa-plug"
                theme="info"
            />
        </div>
        <div class="col-lg-4 col-12">
            <x-adminlte-small-box
                title="MySQL"
                text="Database connected"
                icon="fas fa-database"
                theme="success"
            />
        </div>
    </div>
@stop

@section('css')
    <style>
        .content-wrapper {
            background: #111827 !important;
        }

        .card {
            border-radius: 8px;
        }
    </style>
@stop
