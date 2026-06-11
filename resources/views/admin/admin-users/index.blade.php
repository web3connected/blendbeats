@extends('admin.layouts.app', [
    'title' => 'Admin Users',
    'heading' => 'Admin Users',
    'subtitle' => 'Manage administrator accounts.',
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
            <h3 class="card-title">Admin Users</h3>
            <div class="card-tools">
                @can('adminusers.create')
                    <a href="{{ route('admin.admincenter.adminusers.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus mr-1"></i> Create
                    </a>
                @endcan
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($admins as $adminUser)
                        <tr>
                            <td>
                                <a href="{{ route('admin.admincenter.adminusers.show', $adminUser) }}">
                                    {{ $adminUser->name }}
                                </a>
                            </td>
                            <td>{{ $adminUser->email }}</td>
                            <td>
                                @php($role = $adminUser->roles->first())
                                {{ $role?->display_name ?: str($role?->name ?? $adminUser->role)->replace('-', ' ')->headline() }}
                            </td>
                            <td>
                                <span class="badge badge-{{ $adminUser->is_active ? 'success' : 'secondary' }}">
                                    {{ $adminUser->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ optional($adminUser->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.admincenter.adminusers.show', $adminUser) }}" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye mr-1"></i> Show
                                </a>
                                @can('adminusers.update')
                                    <a href="{{ route('admin.admincenter.adminusers.edit', $adminUser) }}" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </a>
                                @endcan
                                @can('adminusers.delete')
                                    <form method="POST" action="{{ route('admin.admincenter.adminusers.destroy', $adminUser) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this admin user?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No admin users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($admins->hasPages())
            <div class="card-footer">
                {{ $admins->links() }}
            </div>
        @endif
    </div>
@endsection
