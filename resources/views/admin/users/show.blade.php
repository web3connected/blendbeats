@extends('admin.layouts.app', [
    'title' => 'User',
    'heading' => $user->name,
    'subtitle' => $user->email,
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">User Details</h3>
            <div class="card-tools">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-4 text-center mb-4 mb-lg-0">
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="img-circle elevation-2 mb-3" style="height: 160px; object-fit: cover; width: 160px;">
                    <h4 class="mb-1">{{ $user->name }}</h4>
                    <p class="text-muted mb-0">{{ ucfirst($user->avatar_source) }} Avatar</p>
                </div>
                <div class="col-lg-8">
                    <table class="table table-striped mb-0">
                        <tbody>
                            <tr><th>ID</th><td>{{ $user->id }}</td></tr>
                            <tr><th>Name</th><td>{{ $user->name }}</td></tr>
                            <tr><th>First Name</th><td>{{ $user->first_name ?: 'Empty' }}</td></tr>
                            <tr><th>Last Name</th><td>{{ $user->last_name ?: 'Empty' }}</td></tr>
                            <tr><th>Email</th><td>{{ $user->email }}</td></tr>
                            <tr><th>Email Verified At</th><td>{{ optional($user->email_verified_at)->format('Y-m-d H:i:s') ?? 'Not verified' }}</td></tr>
                            <tr><th>Avatar</th><td>{{ $user->avatar ?: 'Empty' }}</td></tr>
                            <tr><th>Use Gravatar</th><td>{{ $user->use_gravatar ? 'Yes' : 'No' }}</td></tr>
                            <tr><th>Is Gravatar</th><td>{{ $user->is_gravatar ? 'Yes' : 'No' }}</td></tr>
                            <tr><th>Media Storage Tier</th><td>{{ $user->media_storage_tier }}</td></tr>
                            <tr><th>Password</th><td><span class="text-muted">Stored hash hidden</span></td></tr>
                            <tr><th>Remember Token</th><td>{{ $user->remember_token ? 'Set' : 'Not set' }}</td></tr>
                            <tr><th>Created At</th><td>{{ optional($user->created_at)->format('Y-m-d H:i:s') }}</td></tr>
                            <tr><th>Updated At</th><td>{{ optional($user->updated_at)->format('Y-m-d H:i:s') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
