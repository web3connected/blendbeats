@if (session('status') && session('status_tab') === 'profile')
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any() && $activeTab === 'profile')
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('admin.admincenter.adminusers.update', $adminUser) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="_section" value="profile">
    @include('admin.admin-users.partials.profile-fields', [
        'adminUser' => $adminUser,
        'roles' => $roles,
        'currentRole' => $currentRole,
    ])
    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save Profile</button>
        <a href="{{ route('admin.admincenter.adminusers.show', $adminUser) }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
