@extends('admin.layouts.app', [
    'title' => 'Edit Admin Role',
    'heading' => 'Edit Admin Role',
    'subtitle' => $role->display_name ?: $role->name,
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('admin.admincenter.adminroles.update', $role) }}">
            @method('PUT')
            <div class="card-body">
                @include('admin.admin-roles.partials.form')
            </div>
        </form>
    </div>
@endsection
