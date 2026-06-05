@csrf
@method('PUT')
<input type="hidden" name="form_section" value="details">

@php
    $selectedRole = old('role', $adminUser->roles->first()?->name ?? $adminUser->role ?? 'admin');
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="name">Name</label>
            <input
                type="text"
                id="name"
                name="name"
                class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $adminUser->name) }}"
                required
            >
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', $adminUser->email) }}"
                required
            >
            @error('email')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" class="form-control @error('role') is-invalid @enderror" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected($selectedRole === $role->name)>
                        {{ str($role->name)->replace('-', ' ')->headline() }}
                    </option>
                @endforeach
            </select>
            @error('role')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group pt-md-4">
            <div class="custom-control custom-switch mt-md-2">
                <input
                    type="checkbox"
                    id="is_active"
                    name="is_active"
                    value="1"
                    class="custom-control-input"
                    @checked(old('is_active', $adminUser->is_active))
                >
                <label for="is_active" class="custom-control-label">Active admin account</label>
            </div>
        </div>
    </div>
</div>

<button type="submit" class="btn btn-danger">
    <span class="fas fa-save"></span>
    Save Details
</button>

<a href="{{ route('admin.admin-users.index') }}" class="btn btn-outline-light ml-2">Cancel</a>
