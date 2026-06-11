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
                <a href="{{ route('admin.admincenter.adminusers.edit', $adminUser) }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
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
                            <tr><th>Role</th><td>{{ $adminUser->role }}</td></tr>
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
