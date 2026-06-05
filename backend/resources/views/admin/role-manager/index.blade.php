@extends('adminlte::page')

@section('title', 'Role Manager')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <h1>Role Manager</h1>
        <a href="{{ route('admin.role-manager.create') }}" class="btn btn-danger">
            <span class="fas fa-user-lock"></span>
            New Role
        </a>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->has('role'))
        <div class="alert alert-danger">{{ $errors->first('role') }}</div>
    @endif

    <x-adminlte-card title="Admin Center" theme="dark" icon="fas fa-user-lock">
        <div class="table-responsive">
            <table class="table table-dark table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Permissions</th>
                        <th>Admins</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td>{{ str($role->name)->replace('-', ' ')->headline() }}</td>
                            <td>{{ $role->permissions_count }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.role-manager.edit', $role) }}" class="btn btn-sm btn-outline-light">
                                    Edit
                                </a>
                                <form action="{{ route('admin.role-manager.destroy', $role) }}" method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" @disabled($role->name === 'sys-admin')>
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No admin roles found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $roles->links() }}
        </div>
    </x-adminlte-card>
@stop
