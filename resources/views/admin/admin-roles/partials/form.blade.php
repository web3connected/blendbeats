@csrf

<div class="row">
    <div class="form-group col-md-6">
        <label for="name">Role Name</label>
        <input id="name" type="text" name="name" value="{{ old('name', $role->name) }}" class="form-control @error('name') is-invalid @enderror" required @readonly($role->name === 'super-admin')>
        <small class="form-text text-muted">Use lowercase letters, numbers, and hyphens, like content-manager.</small>
        @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="display_name">Display Name</label>
        <input id="display_name" type="text" name="display_name" value="{{ old('display_name', $role->display_name) }}" class="form-control @error('display_name') is-invalid @enderror">
        @error('display_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $role->description) }}</textarea>
    @error('description') <span class="invalid-feedback">{{ $message }}</span> @enderror
</div>

<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="hidden" name="is_system" value="0">
        <input id="is_system" type="checkbox" name="is_system" value="1" class="custom-control-input" @checked(old('is_system', $role->is_system))>
        <label class="custom-control-label" for="is_system">System Role</label>
    </div>
</div>

<div class="form-group">
    <label>Permission Assignments</label>
    @error('permissions') <div class="text-danger mb-2">{{ $message }}</div> @enderror

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
                                <input id="permission_{{ $permission->id }}" type="checkbox" name="permissions[]" value="{{ $permission->name }}" class="custom-control-input" @checked(in_array($permission->name, old('permissions', $role->permissions->pluck('name')->all()), true))>
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

<div class="mt-3">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i> Save Role
    </button>
    <a href="{{ route('admin.admincenter.adminroles.index') }}" class="btn btn-secondary">Cancel</a>
</div>
