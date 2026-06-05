@extends('adminlte::page')

@section('title', 'Edit User Account')

@section('content_header')
    <h1>Edit User Account</h1>
@stop

@section('content')
    @php
        $activeTab = old('form_section', session('user_account_tab', 'details'));
        $shouldRestoreStoredTab = ! old('form_section') && ! session()->has('user_account_tab');
    @endphp

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-adminlte-card title="User Account" theme="dark" icon="fas fa-user">
        <ul class="nav nav-tabs" id="user-account-tabs" role="tablist">
            <li class="nav-item">
                <a
                    class="nav-link @if ($activeTab === 'details') active @endif"
                    id="details-tab"
                    data-toggle="pill"
                    href="#details"
                    role="tab"
                    aria-controls="details"
                    aria-selected="{{ $activeTab === 'details' ? 'true' : 'false' }}"
                >
                    Details
                </a>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link @if ($activeTab === 'password') active @endif"
                    id="password-tab"
                    data-toggle="pill"
                    href="#password"
                    role="tab"
                    aria-controls="password"
                    aria-selected="{{ $activeTab === 'password' ? 'true' : 'false' }}"
                >
                    Password
                </a>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link @if ($activeTab === 'avatar') active @endif"
                    id="avatar-tab"
                    data-toggle="pill"
                    href="#avatar"
                    role="tab"
                    aria-controls="avatar"
                    aria-selected="{{ $activeTab === 'avatar' ? 'true' : 'false' }}"
                >
                    Avatar
                </a>
            </li>
        </ul>

        <div class="tab-content pt-3" id="user-account-tab-content">
            <div
                class="tab-pane fade @if ($activeTab === 'details') show active @endif"
                id="details"
                role="tabpanel"
                aria-labelledby="details-tab"
            >
                <form action="{{ route('admin.user-accounts.update', $userAccount) }}" method="post">
                    @include('admin.user-accounts._details_form')
                </form>
            </div>

            <div
                class="tab-pane fade @if ($activeTab === 'password') show active @endif"
                id="password"
                role="tabpanel"
                aria-labelledby="password-tab"
            >
                <form action="{{ route('admin.user-accounts.update', $userAccount) }}" method="post">
                    @include('admin.user-accounts._password_form')
                </form>
            </div>

            <div
                class="tab-pane fade @if ($activeTab === 'avatar') show active @endif"
                id="avatar"
                role="tabpanel"
                aria-labelledby="avatar-tab"
            >
                <form action="{{ route('admin.user-accounts.update', $userAccount) }}" method="post" enctype="multipart/form-data">
                    @include('admin.user-accounts._avatar_form')
                </form>
            </div>
        </div>
    </x-adminlte-card>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const storageKey = 'user-account-edit-tab-{{ $userAccount->id }}';
            const shouldRestoreStoredTab = @json($shouldRestoreStoredTab);
            const tabs = document.querySelectorAll('#user-account-tabs .nav-link');

            function readStoredTab() {
                try {
                    return localStorage.getItem(storageKey);
                } catch (error) {
                    return null;
                }
            }

            function storeTab(tab) {
                try {
                    localStorage.setItem(storageKey, tab.getAttribute('href'));
                } catch (error) {
                    // Ignore storage failures; tab behavior should still work.
                }
            }

            function showTab(tab) {
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.tab) {
                    window.jQuery(tab).tab('show');
                    return;
                }

                tabs.forEach(function (otherTab) {
                    const pane = document.querySelector(otherTab.getAttribute('href'));
                    const isActive = otherTab === tab;

                    otherTab.classList.toggle('active', isActive);
                    otherTab.setAttribute('aria-selected', isActive ? 'true' : 'false');

                    if (pane) {
                        pane.classList.toggle('show', isActive);
                        pane.classList.toggle('active', isActive);
                    }
                });
            }

            if (shouldRestoreStoredTab) {
                const storedTab = readStoredTab();
                const storedTabLink = storedTab
                    ? document.querySelector('#user-account-tabs .nav-link[href="' + storedTab + '"]')
                    : null;

                if (storedTabLink) {
                    showTab(storedTabLink);
                }
            } else {
                const activeTab = document.querySelector('#user-account-tabs .nav-link.active');

                if (activeTab) {
                    storeTab(activeTab);
                }
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('shown.bs.tab', function (event) {
                    storeTab(event.target);
                });

                tab.addEventListener('click', function () {
                    storeTab(tab);
                });
            });

            document.querySelectorAll('#user-account-tab-content form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    const activeTab = document.querySelector('#user-account-tabs .nav-link.active');

                    if (activeTab) {
                        storeTab(activeTab);
                    }
                });
            });

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
