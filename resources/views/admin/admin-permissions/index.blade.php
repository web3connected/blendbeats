@extends('admin.layouts.app', [
    'title' => 'Permissions',
    'heading' => 'Permissions',
    'subtitle' => 'Admin Center authorization capabilities.',
])

@section('admin_content')
    <div class="row">
        <div class="col-md-4">
            <div class="info-box bg-dark">
                <span class="info-box-icon bg-primary"><i class="fas fa-key"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Permissions</span>
                    <span class="info-box-number">{{ $permissionCount }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box bg-dark">
                <span class="info-box-icon bg-info"><i class="fas fa-user-lock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Roles</span>
                    <span class="info-box-number">{{ $roleCount }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box bg-dark">
                <span class="info-box-icon bg-success"><i class="fas fa-shield-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Guard</span>
                    <span class="info-box-number">admin</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            @forelse ($permissionsByModule as $module => $permissions)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ str($module)->replace('-', ' ')->headline() }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($permissions as $permission)
                                <div class="col-md-6">
                                    <div class="border rounded p-3 mb-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <strong>{{ $permission->name }}</strong>
                                            <span class="badge badge-primary">{{ $permission->guard_name }}</span>
                                        </div>
                                        <div class="mt-2">
                                            @forelse ($permission->roles as $role)
                                                <span class="badge badge-info mb-1">{{ $role->display_name ?: $role->name }}</span>
                                            @empty
                                                <span class="text-muted">No roles assigned.</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="alert alert-warning">No admin permissions have been seeded yet.</div>
            @endforelse
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Role Coverage</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th class="text-right">Permissions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.admincenter.adminroles.show', $role) }}">
                                            {{ $role->display_name ?: str($role->name)->replace('-', ' ')->headline() }}
                                        </a>
                                        <div class="text-muted small">{{ $role->name }}</div>
                                    </td>
                                    <td class="text-right">
                                        <span class="badge badge-secondary">{{ $role->permissions_count }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
