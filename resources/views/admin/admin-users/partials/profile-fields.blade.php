@php($adminUser = $adminUser ?? null)
@php($currentRole = $currentRole ?? $adminUser?->roles?->first())
@php($selectedRoleId = old('role_id', $currentRole?->id))

<div class="row">
    <div class="form-group col-md-6">
        <label for="name">Name</label>
        <input id="name" type="text" name="name" value="{{ old('name', $adminUser?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
        @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $adminUser?->email) }}" class="form-control @error('email') is-invalid @enderror" required>
        @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>
</div>

<div class="row">
    <div class="form-group col-md-6">
        <label for="email_verified_at">Email Verified At</label>
        <input id="email_verified_at" type="datetime-local" name="email_verified_at" value="{{ old('email_verified_at', optional($adminUser?->email_verified_at)->format('Y-m-d\\TH:i')) }}" class="form-control @error('email_verified_at') is-invalid @enderror">
        @error('email_verified_at') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="role_id">Role</label>
        <select id="role_id" name="role_id" class="form-control @error('role_id') is-invalid @enderror" required>
            <option value="">Select role</option>
            @foreach ($roles ?? [] as $role)
                <option value="{{ $role->id }}" @selected((string) $selectedRoleId === (string) $role->id)>
                    {{ $role->name }}{{ $role->display_name ? ' - '.$role->display_name : '' }}
                </option>
            @endforeach
        </select>
        @error('role_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>
</div>

<div class="row">
    <div class="form-group col-md-6">
        <div class="custom-control custom-switch">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" type="checkbox" name="is_active" value="1" class="custom-control-input" @checked(old('is_active', $adminUser?->is_active ?? true))>
            <label class="custom-control-label" for="is_active">Active</label>
        </div>
    </div>
</div>
