@extends('adminlte::page')

@section('title', 'Admin Users')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <h1>Admin Users</h1>
        <a href="{{ route('admin.admin-users.create') }}" class="btn btn-danger">
            <span class="fas fa-user-plus"></span>
            New Admin
        </a>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->has('admin_user'))
        <div class="alert alert-danger">{{ $errors->first('admin_user') }}</div>
    @endif

    <x-adminlte-card title="Admin Center" theme="dark" icon="fas fa-users-cog">
        <div class="table-responsive">
            <table class="table table-dark table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($admins as $adminUser)
                        <tr>
                            <td>
                                <img src="{{ $adminUser->adminlte_image() }}" alt="" class="img-circle mr-2" width="32" height="32">
                                {{ $adminUser->name }}
                            </td>
                            <td>{{ $adminUser->email }}</td>
                            <td>{{ $adminUser->adminlte_desc() }}</td>
                            <td>
                                @if ($adminUser->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.admin-users.edit', $adminUser) }}" class="btn btn-sm btn-outline-light">
                                    Edit
                                </a>
                                <form action="{{ route('admin.admin-users.destroy', $adminUser) }}" method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No admin users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $admins->links() }}
        </div>
    </x-adminlte-card>
@stop
