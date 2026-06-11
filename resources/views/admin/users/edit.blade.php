@extends('admin.layouts.app', [
    'title' => 'Edit User',
    'heading' => 'Edit User',
    'subtitle' => $user->email,
])

@section('admin_content')
    @php($activeTab = old('_section', session('status_tab', 'profile')))

    <div class="card card-primary card-outline card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="user-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link @if ($activeTab === 'profile') active @endif" id="profile-tab" data-toggle="pill" href="#profile-info" role="tab">
                        Profile Info
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if ($activeTab === 'password') active @endif" id="password-tab" data-toggle="pill" href="#password-reset" role="tab">
                        Password Reset
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if ($activeTab === 'avatar') active @endif" id="avatar-tab" data-toggle="pill" href="#avatar-upload" role="tab">
                        Avatar Upload
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="user-tabs-content">
                <div class="tab-pane fade @if ($activeTab === 'profile') show active @endif" id="profile-info" role="tabpanel">
                    @include('admin.users.partials.profile-tab', [
                        'activeTab' => $activeTab,
                        'user' => $user,
                    ])
                </div>

                <div class="tab-pane fade @if ($activeTab === 'password') show active @endif" id="password-reset" role="tabpanel">
                    @include('admin.users.partials.password-tab', [
                        'activeTab' => $activeTab,
                        'user' => $user,
                    ])
                </div>

                <div
                    class="tab-pane fade @if ($activeTab === 'avatar') show active @endif"
                    id="avatar-upload"
                    role="tabpanel"
                    data-avatar-panel
                    data-current-avatar="{{ $user->avatar_url }}"
                    data-uploaded-avatar="{{ $user->getUploadedAvatarUrl() ?? '' }}"
                    data-gravatar="{{ $user->getGravatarUrl(192) }}"
                    data-initials="{{ $user->getInitials() }}"
                    data-name="{{ $user->name }}"
                    data-use-gravatar="{{ $user->usesGravatar() ? '1' : '0' }}"
                >
                    @include('admin.users.partials.avatar-tab', [
                        'activeTab' => $activeTab,
                        'user' => $user,
                    ])
                </div>
            </div>
        </div>
    </div>

    @include('admin.users.partials.avatar-assets')
@endsection
