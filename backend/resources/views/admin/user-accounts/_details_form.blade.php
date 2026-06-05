@csrf
@method('PUT')
<input type="hidden" name="form_section" value="details">

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="name">Name</label>
            <input
                type="text"
                id="name"
                name="name"
                class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $userAccount->name) }}"
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
                value="{{ old('email', $userAccount->email) }}"
                required
            >
            @error('email')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<button type="submit" class="btn btn-danger">
    <span class="fas fa-save"></span>
    Save Details
</button>

<a href="{{ route('admin.user-accounts.index') }}" class="btn btn-outline-light ml-2">Cancel</a>
