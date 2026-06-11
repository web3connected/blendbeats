@extends('admin.layouts.app', [
    'title' => 'Admin Roles',
    'heading' => 'Admin Roles',
    'subtitle' => 'Manage administrator roles and permissions.',
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Roles</h3>
            <div class="card-tools">
                <a href="{{ route('admin.admincenter.adminroles.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i> Create
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Role ID</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>User Count</th>
                        <th>Created Date</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td>{{ $role->id }}</td>
                            <td>
                                <a href="{{ route('admin.admincenter.adminroles.show', $role) }}">
                                    {{ $role->display_name ?: str($role->name)->replace('-', ' ')->headline() }}
                                </a>
                                <div class="text-muted small">{{ $role->name }}</div>
                            </td>
                            <td>{{ $role->description ?: 'No description.' }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td>{{ optional($role->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.admincenter.adminroles.show', $role) }}" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                <a href="{{ route('admin.admincenter.adminroles.edit', $role) }}" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('admin.admincenter.adminroles.destroy', $role) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this admin role?')" @disabled($role->is_system || $role->name === 'super-admin')>
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No admin roles found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($roles->hasPages())
            <div class="card-footer">
                {{ $roles->links() }}
            </div>
        @endif
    </div>
@endsection
