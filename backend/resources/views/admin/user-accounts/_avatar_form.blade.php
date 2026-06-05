@csrf
@method('PUT')
<input type="hidden" name="form_section" value="avatar">

@php
    $uploadedAvatarUrl = $userAccount->getUploadedAvatarUrl();
    $generatedAvatarUrl = $userAccount->getGeneratedAvatarUrl(160);
    $customAvatarUrl = $uploadedAvatarUrl ?: $generatedAvatarUrl;
@endphp

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="avatar">Upload Avatar</label>
            <input
                type="file"
                id="avatar"
                name="avatar"
                class="form-control-file @error('avatar') is-invalid @enderror"
                accept="image/*"
            >
            <small class="form-text text-muted">When Gravatar is off, the uploaded image is used first.</small>
            @error('avatar')
                <span class="invalid-feedback d-block">{{ $message }}</span>
            @enderror
        </div>

        @if ($userAccount->avatar)
            <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" id="remove_avatar" name="remove_avatar" value="1" class="custom-control-input">
                <label for="remove_avatar" class="custom-control-label">Remove uploaded avatar</label>
            </div>
        @endif

        <dl class="row mb-0">
            <dt class="col-sm-4">Uploaded Value</dt>
            <dd class="col-sm-8 text-break">{{ $userAccount->avatar ?: 'No uploaded avatar' }}</dd>

            <dt class="col-sm-4">Initials</dt>
            <dd class="col-sm-8">{{ $userAccount->getInitials() }}</dd>
        </dl>
    </div>

    <div class="col-lg-6">
        <div class="d-flex align-items-center mb-3">
            <img
                id="avatar-preview"
                src="{{ $userAccount->getAvatarUrl(160) }}"
                alt="{{ $userAccount->full_name ?: $userAccount->email }}"
                class="img-circle elevation-2 mr-3"
                width="112"
                height="112"
                data-gravatar-url="{{ $userAccount->getGravatarUrl(160) }}"
                data-custom-url="{{ $customAvatarUrl }}"
                data-generated-url="{{ $generatedAvatarUrl }}"
                data-uploaded-url="{{ $uploadedAvatarUrl }}"
            >
            <div>
                <div class="h5 mb-1">{{ $userAccount->full_name ?: $userAccount->email }}</div>
                <div class="text-muted">{{ $userAccount->email }}</div>
            </div>
        </div>

        <div class="custom-control custom-switch mb-3">
            <input
                type="checkbox"
                id="use_gravatar"
                name="use_gravatar"
                value="1"
                class="custom-control-input"
                @checked(old('use_gravatar', $userAccount->use_gravatar))
            >
            <label for="use_gravatar" class="custom-control-label">Use Gravatar</label>
        </div>

        <div class="alert alert-secondary mb-0">
            If Gravatar is on, the user email controls the image. If Gravatar is off, the uploaded avatar is used; when there is no upload, AvatarTrait uses a generated initials image.
        </div>
    </div>
</div>

<button type="submit" class="btn btn-danger mt-3">
    <span class="fas fa-image"></span>
    Save Avatar
</button>
