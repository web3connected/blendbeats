@csrf

<div class="form-group">
    <label for="name">Role Name</label>
    <input
        type="text"
        id="name"
        name="name"
        class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $role->name) }}"
        required
        @readonly($role->name === 'sys-admin')
    >
    <small class="form-text text-muted">Use lowercase letters, numbers, and hyphens, like content-manager.</small>
    @error('name')
        <span class="invalid-feedback">{{ $message }}</span>
    @enderror
</div>

<div class="form-group">
    <label>Permissions</label>
    @error('permissions')
        <div class="text-danger mb-2">{{ $message }}</div>
    @enderror

    <div class="row">
        @foreach ($permissions as $group => $groupPermissions)
            <div class="col-lg-4 col-md-6">
                <div class="card bg-gray-dark border-secondary">
                    <div class="card-header py-2">
                        <strong>{{ str($group)->replace('-', ' ')->headline() }}</strong>
                    </div>
                    <div class="card-body py-2">
                        @foreach ($groupPermissions as $permission)
                            <div class="custom-control custom-checkbox">
                                <input
                                    type="checkbox"
                                    id="permission_{{ $permission->id }}"
                                    name="permissions[]"
                                    value="{{ $permission->name }}"
                                    class="custom-control-input"
                                    @checked(in_array($permission->name, old('permissions', $role->permissions->pluck('name')->all()), true))
                                >
                                <label for="permission_{{ $permission->id }}" class="custom-control-label">
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

<button type="submit" class="btn btn-danger">
    <span class="fas fa-save"></span>
    Save Role
</button>

<a href="{{ route('admin.role-manager.index') }}" class="btn btn-outline-light ml-2">Cancel</a>
