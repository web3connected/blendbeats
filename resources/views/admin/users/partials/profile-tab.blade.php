@if (session('status') && session('status_tab') === 'profile')
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any() && $activeTab === 'profile')
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('admin.users.update', $user) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="_section" value="profile">
    @include('admin.users.partials.profile-fields', ['user' => $user])
    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save Profile</button>
        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
