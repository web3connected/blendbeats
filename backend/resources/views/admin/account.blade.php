@extends('adminlte::page')

@php
    $activeTab = match (true) {
        request('tab') === 'avatar' || session()->has('avatar_status') || $errors->has('avatar') => 'avatar',
        request('tab') === 'password' || session()->has('password_status') || $errors->has('current_password') || $errors->has('password') => 'password',
        default => 'profile',
    };
@endphp

@section('title', 'Admin Account')

@section('content_header')
    <h1>Account</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-lg-4">
            <x-adminlte-profile-widget
                name="{{ $admin->name }}"
                desc="{{ $admin->adminlte_desc() }}"
                img="{{ $admin->adminlte_image() }}"
                theme="danger"
                header-class="text-center"
            />
        </div>

        <div class="col-lg-8">
            <x-adminlte-card theme="dark" icon="fas fa-user-shield">
                <ul class="nav nav-tabs" id="account-tabs" role="tablist">
                    <li class="nav-item">
                        <a
                            class="nav-link {{ $activeTab === 'profile' ? 'active' : '' }}"
                            id="profile-tab"
                            data-toggle="pill"
                            href="#profile-panel"
                            role="tab"
                            aria-controls="profile-panel"
                            aria-selected="{{ $activeTab === 'profile' ? 'true' : 'false' }}"
                        >
                            Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a
                            class="nav-link {{ $activeTab === 'password' ? 'active' : '' }}"
                            id="password-tab"
                            data-toggle="pill"
                            href="#password-panel"
                            role="tab"
                            aria-controls="password-panel"
                            aria-selected="{{ $activeTab === 'password' ? 'true' : 'false' }}"
                        >
                            Password
                        </a>
                    </li>
                    <li class="nav-item">
                        <a
                            class="nav-link {{ $activeTab === 'avatar' ? 'active' : '' }}"
                            id="avatar-tab"
                            data-toggle="pill"
                            href="#avatar-panel"
                            role="tab"
                            aria-controls="avatar-panel"
                            aria-selected="{{ $activeTab === 'avatar' ? 'true' : 'false' }}"
                        >
                            Avatar
                        </a>
                    </li>
                </ul>

                <div class="tab-content pt-4" id="account-tab-content">
                    <div
                        class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}"
                        id="profile-panel"
                        role="tabpanel"
                        aria-labelledby="profile-tab"
                    >
                        @if (session('profile_status'))
                            <div class="alert alert-success">{{ session('profile_status') }}</div>
                        @endif

                        <form action="{{ route('admin.account.profile') }}" method="post">
                            @csrf

                            <div class="form-group">
                                <label for="name">Name</label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $admin->name) }}"
                                    required
                                >
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email', $admin->email) }}"
                                    required
                                >
                                @error('email')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <dl class="row mb-4">
                                <dt class="col-sm-4">Role</dt>
                                <dd class="col-sm-8">{{ $admin->adminlte_desc() }}</dd>

                                <dt class="col-sm-4">Status</dt>
                                <dd class="col-sm-8">
                                    @if ($admin->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Created</dt>
                                <dd class="col-sm-8">{{ optional($admin->created_at)->format('M j, Y g:i A') ?? 'Not available' }}</dd>

                                <dt class="col-sm-4">Updated</dt>
                                <dd class="col-sm-8">{{ optional($admin->updated_at)->format('M j, Y g:i A') ?? 'Not available' }}</dd>
                            </dl>

                            <button type="submit" class="btn btn-danger">
                                <span class="fas fa-save"></span>
                                Save Profile
                            </button>
                        </form>
                    </div>

                    <div
                        class="tab-pane fade {{ $activeTab === 'password' ? 'show active' : '' }}"
                        id="password-panel"
                        role="tabpanel"
                        aria-labelledby="password-tab"
                    >
                        @if (session('password_status'))
                            <div class="alert alert-success">{{ session('password_status') }}</div>
                        @endif

                        <form action="{{ route('admin.account.password') }}" method="post">
                            @csrf

                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input
                                    type="password"
                                    id="current_password"
                                    name="current_password"
                                    class="form-control @error('current_password') is-invalid @enderror"
                                    autocomplete="current-password"
                                    required
                                >
                                @error('current_password')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

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

                            <div class="form-group">
                                <label for="password_confirmation">Confirm New Password</label>
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-danger">
                                <span class="fas fa-key"></span>
                                Update Password
                            </button>
                        </form>
                    </div>

                    <div
                        class="tab-pane fade {{ $activeTab === 'avatar' ? 'show active' : '' }}"
                        id="avatar-panel"
                        role="tabpanel"
                        aria-labelledby="avatar-tab"
                    >
                        @php
                            $uploadedAvatarUrl = $admin->getUploadedAvatarUrl();
                            $generatedAvatarUrl = $admin->getGeneratedAvatarUrl(160);
                            $customAvatarUrl = $uploadedAvatarUrl ?: $generatedAvatarUrl;
                        @endphp

                        @if (session('avatar_status'))
                            <div class="alert alert-success">{{ session('avatar_status') }}</div>
                        @endif

                        <form action="{{ route('admin.account.avatar') }}" method="post" enctype="multipart/form-data">
                            @csrf

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

                                    @if ($admin->avatar)
                                        <div class="custom-control custom-checkbox mb-3">
                                            <input type="checkbox" id="remove_avatar" name="remove_avatar" value="1" class="custom-control-input">
                                            <label for="remove_avatar" class="custom-control-label">Remove uploaded avatar</label>
                                        </div>
                                    @endif

                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">Uploaded Value</dt>
                                        <dd class="col-sm-8 text-break">{{ $admin->avatar ?: 'No uploaded avatar' }}</dd>

                                        <dt class="col-sm-4">Initials</dt>
                                        <dd class="col-sm-8">{{ $admin->getInitials() }}</dd>
                                    </dl>
                                </div>

                                <div class="col-lg-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <img
                                            id="avatar-preview"
                                            src="{{ $admin->getAvatarUrl(160) }}"
                                            alt="{{ $admin->full_name ?: $admin->email }}"
                                            class="img-circle elevation-2 mr-3"
                                            width="112"
                                            height="112"
                                            data-gravatar-url="{{ $admin->getGravatarUrl(160) }}"
                                            data-custom-url="{{ $customAvatarUrl }}"
                                            data-generated-url="{{ $generatedAvatarUrl }}"
                                            data-uploaded-url="{{ $uploadedAvatarUrl }}"
                                        >
                                        <div>
                                            <div class="h5 mb-1">{{ $admin->full_name ?: $admin->email }}</div>
                                            <div class="text-muted">{{ $admin->email }}</div>
                                        </div>
                                    </div>

                                    <div class="custom-control custom-switch mb-3">
                                        <input
                                            type="checkbox"
                                            id="use_gravatar"
                                            name="use_gravatar"
                                            value="1"
                                            class="custom-control-input"
                                            @checked(old('use_gravatar', $admin->use_gravatar))
                                        >
                                        <label for="use_gravatar" class="custom-control-label">Use Gravatar</label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-danger mt-3">
                                <span class="fas fa-image"></span>
                                Save Avatar
                            </button>
                        </form>
                    </div>
                </div>
            </x-adminlte-card>
        </div>
    </div>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const avatarInput = document.getElementById('avatar');
            const gravatarToggle = document.getElementById('use_gravatar');
            const preview = document.getElementById('avatar-preview');
            const removeAvatar = document.getElementById('remove_avatar');

            if (! avatarInput || ! gravatarToggle || ! preview) {
                return;
            }

            let selectedUploadUrl = null;

            function customSourceUrl() {
                if (removeAvatar && removeAvatar.checked) {
                    return preview.dataset.generatedUrl;
                }

                return selectedUploadUrl || preview.dataset.uploadedUrl || preview.dataset.generatedUrl;
            }

            function syncPreview() {
                preview.src = gravatarToggle.checked ? preview.dataset.gravatarUrl : customSourceUrl();
            }

            gravatarToggle.addEventListener('change', syncPreview);

            avatarInput.addEventListener('change', function () {
                if (selectedUploadUrl) {
                    URL.revokeObjectURL(selectedUploadUrl);
                }

                selectedUploadUrl = avatarInput.files.length > 0
                    ? URL.createObjectURL(avatarInput.files[0])
                    : null;

                syncPreview();
            });

            if (removeAvatar) {
                removeAvatar.addEventListener('change', syncPreview);
            }
        });
    </script>
@stop

@section('css')
    <style>
        .content-wrapper {
            background: #111827 !important;
        }

        .profile-user-img {
            background: #111827;
        }

        .nav-tabs {
            border-bottom-color: #374151;
        }

        .nav-tabs .nav-link.active {
            border-color: #374151 #374151 #1f2937;
            background: #1f2937;
            color: #fff;
        }
    </style>
@stop
