@extends('admin.layouts.app', [
    'title' => 'User Accounts',
    'heading' => 'User Accounts',
    'subtitle' => 'Manage regular application users.',
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
            <h3 class="card-title">User Accounts</h3>
            <div class="card-tools">
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i> Create
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Avatar</th>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Gravatar</th>
                        <th>Storage Tier</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td><img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="img-circle" style="height: 40px; object-fit: cover; width: 40px;"></td>
                            <td>{{ $user->id }}</td>
                            <td>
                                <a href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a>
                                <div class="text-muted small">{{ trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'No split name' }}</div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->use_gravatar || $user->is_gravatar ? 'On' : 'Off' }}</td>
                            <td>{{ $user->media_storage_tier }}</td>
                            <td>{{ optional($user->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye mr-1"></i> Show
                                </a>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
