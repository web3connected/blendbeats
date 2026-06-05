@csrf
@method('PUT')
<input type="hidden" name="form_section" value="password">

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="password">New Password</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                autocomplete="new-password"
                required
            >
            @error('password')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control"
                autocomplete="new-password"
                required
            >
        </div>
    </div>
</div>

<button type="submit" class="btn btn-danger">
    <span class="fas fa-key"></span>
    Save Password
</button>
