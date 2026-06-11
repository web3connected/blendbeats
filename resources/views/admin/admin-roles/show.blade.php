@extends('admin.layouts.app', [
    'title' => 'Admin Role',
    'heading' => $role->display_name ?: str($role->name)->replace('-', ' ')->headline(),
    'subtitle' => $role->name,
])

@section('admin_content')
    @php($activeTab = request('tab', 'role-info'))

    <div class="card card-primary card-outline card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="admin-role-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link @if ($activeTab === 'role-info') active @endif" data-toggle="pill" href="#role-info" role="tab">Role Information</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if ($activeTab === 'permissions') active @endif" data-toggle="pill" href="#assigned-permissions" role="tab">Assigned Permissions</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if ($activeTab === 'users') active @endif" data-toggle="pill" href="#assigned-users" role="tab">Assigned Users</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade @if ($activeTab === 'role-info') show active @endif" id="role-info" role="tabpanel">
                    <div class="mb-3">
                        <a href="{{ route('admin.admincenter.adminroles.edit', $role) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </a>
                        <a href="{{ route('admin.admincenter.adminroles.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Back
                        </a>
                    </div>
                    <table class="table table-striped">
                        <tbody>
                            <tr><th>Role Name</th><td>{{ $role->name }}</td></tr>
                            <tr><th>Display Name</th><td>{{ $role->display_name ?: 'Not set' }}</td></tr>
                            <tr><th>Description</th><td>{{ $role->description ?: 'No description.' }}</td></tr>
                            <tr><th>System Role Status</th><td>{{ $role->is_system ? 'System Role' : 'Custom Role' }}</td></tr>
                            <tr><th>Created Date</th><td>{{ optional($role->created_at)->format('Y-m-d H:i:s') }}</td></tr>
                            <tr><th>Updated Date</th><td>{{ optional($role->updated_at)->format('Y-m-d H:i:s') }}</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade @if ($activeTab === 'permissions') show active @endif" id="assigned-permissions" role="tabpanel">
                    <div class="row">
                        @foreach ($permissions as $module => $modulePermissions)
                            <div class="col-lg-4 col-md-6">
                                <div class="card bg-dark">
                                    <div class="card-header py-2">
                                        <strong>{{ str($module)->replace('-', ' ')->headline() }}</strong>
                                    </div>
                                    <div class="card-body py-2">
                                        @foreach ($modulePermissions as $permission)
                                            <div class="custom-control custom-checkbox">
                                                <input id="permission_show_{{ $permission->id }}" type="checkbox" class="custom-control-input" @checked($role->permissions->contains('id', $permission->id)) disabled>
                                                <label for="permission_show_{{ $permission->id }}" class="custom-control-label">
                                                    {{ str($permission->name)->after('.')->replace('-', ' ')->headline() }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="tab-pane fade @if ($activeTab === 'users') show active @endif" id="assigned-users" role="tabpanel">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Avatar</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($role->users as $adminUser)
                                <tr>
                                    <td><img src="{{ $adminUser->avatar_url }}" alt="{{ $adminUser->name }}" class="img-circle" style="height: 40px; object-fit: cover; width: 40px;"></td>
                                    <td><a href="{{ route('admin.admincenter.adminusers.show', $adminUser) }}">{{ $adminUser->name }}</a></td>
                                    <td>{{ $adminUser->email }}</td>
                                    <td>{{ $adminUser->is_active ? 'Active' : 'Inactive' }}</td>
                                    <td>{{ $adminUser->last_login_at ? $adminUser->last_login_at->format('Y-m-d H:i') : 'Not tracked' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No admin users are assigned to this role.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
