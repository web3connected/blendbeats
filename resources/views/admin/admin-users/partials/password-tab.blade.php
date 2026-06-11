@if (session('status') && session('status_tab') === 'password')
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any() && $activeTab === 'password')
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('admin.admincenter.adminusers.update', $adminUser) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="_section" value="password">
    <div class="row">
        <div class="form-group col-md-6">
            <label for="new_password">New Password</label>
            <input id="new_password" type="password" name="new_password" class="form-control @error('new_password') is-invalid @enderror" required>
            @error('new_password')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
        <div class="form-group col-md-6">
            <label for="new_password_confirmation">Confirm New Password</label>
            <input id="new_password_confirmation" type="password" name="new_password_confirmation" class="form-control" required>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Reset Password</button>
</form>
