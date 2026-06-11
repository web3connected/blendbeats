@extends('admin.layouts.app', [
    'title' => 'Admin User',
    'heading' => $adminUser->name,
    'subtitle' => $adminUser->email,
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Admin User Details</h3>
            <div class="card-tools">
                @can('adminusers.update')
                    <a href="{{ route('admin.admincenter.adminusers.edit', $adminUser) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit mr-1"></i> Edit
                    </a>
                @endcan
                <a href="{{ route('admin.admincenter.adminusers.index') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-4 text-center mb-4 mb-lg-0">
                    <img src="{{ $adminUser->avatar_url }}" alt="{{ $adminUser->name }}" class="img-circle elevation-2 mb-3" style="height: 160px; object-fit: cover; width: 160px;">
                    <h4 class="mb-1">{{ $adminUser->name }}</h4>
                    <p class="text-muted mb-0">{{ ucfirst($adminUser->avatar_source) }} Avatar</p>
                </div>
                <div class="col-lg-8">
                    <table class="table table-striped mb-0">
                        <tbody>
                            <tr><th>ID</th><td>{{ $adminUser->id }}</td></tr>
                            <tr><th>Name</th><td>{{ $adminUser->name }}</td></tr>
                            <tr><th>Email</th><td>{{ $adminUser->email }}</td></tr>
                            <tr><th>Email Verified At</th><td>{{ optional($adminUser->email_verified_at)->format('Y-m-d H:i:s') ?? 'Not verified' }}</td></tr>
                            <tr><th>Avatar</th><td>{{ $adminUser->avatar ?: 'Empty' }}</td></tr>
                            <tr><th>Use Gravatar</th><td>{{ $adminUser->use_gravatar ? 'Yes' : 'No' }}</td></tr>
                            <tr><th>Password</th><td><span class="text-muted">Stored hash hidden</span></td></tr>
                            <tr>
                                <th>Role Information</th>
                                <td>
                                    <div class="card bg-dark mb-0">
                                        <div class="card-body">
                                            @if ($currentRole)
                                                <h5 class="mb-1">{{ $currentRole->display_name ?: str($currentRole->name)->replace('-', ' ')->headline() }}</h5>
                                                <div class="mb-2">
                                                    @foreach ($adminUser->roles as $role)
                                                        <span class="badge badge-primary">{{ $role->name }}</span>
                                                    @endforeach
                                                </div>
                                                <p class="text-muted mb-2">{{ $currentRole->description ?: 'No description.' }}</p>
                                                @php($effectivePermissions = $adminUser->getAllPermissions()->sortBy('name'))
                                                <p class="mb-2">Permission Count: {{ $effectivePermissions->count() }}</p>
                                                <a href="{{ route('admin.admincenter.adminroles.show', $currentRole) }}" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-user-lock mr-1"></i> View Role
                                                </a>
                                            @else
                                                <span class="text-muted">No role assignment found.</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Effective Permissions</th>
                                <td>
                                    @php($effectivePermissions = $effectivePermissions ?? $adminUser->getAllPermissions()->sortBy('name'))
                                    @forelse ($effectivePermissions as $permission)
                                        <span class="badge badge-info mb-1">{{ $permission->name }}</span>
                                    @empty
                                        <span class="text-muted">No permissions assigned.</span>
                                    @endforelse
                                </td>
                            </tr>
                            <tr><th>Active</th><td>{{ $adminUser->is_active ? 'Yes' : 'No' }}</td></tr>
                            <tr><th>Remember Token</th><td>{{ $adminUser->remember_token ? 'Set' : 'Not set' }}</td></tr>
                            <tr><th>Created At</th><td>{{ optional($adminUser->created_at)->format('Y-m-d H:i:s') }}</td></tr>
                            <tr><th>Updated At</th><td>{{ optional($adminUser->updated_at)->format('Y-m-d H:i:s') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
