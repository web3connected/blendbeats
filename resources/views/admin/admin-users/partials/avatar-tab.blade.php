@if (session('status') && session('status_tab') === 'avatar')
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any() && $activeTab === 'avatar')
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@can('adminusers.manage-avatar')
<div class="row">
    <div class="col-lg-6">
        <div class="card bg-dark">
            <div class="card-header">
                <h3 class="card-title">Avatar Preview</h3>
            </div>
            <div class="card-body text-center">
                <div class="admin-avatar-preview mx-auto mb-3">
                    <img data-avatar-image src="" alt="{{ $adminUser->name }}" class="img-circle elevation-2">
                    <div data-avatar-initials class="admin-avatar-initials"></div>
                </div>
                <p class="mb-1">
                    <strong data-avatar-source>{{ ucfirst($adminUser->avatar_source) }}</strong>
                </p>
                <p class="text-muted mb-0">
                    {{ $adminUser->avatar ? $adminUser->avatar : 'No uploaded avatar path is stored.' }}
                </p>
                <table class="table table-sm table-dark mt-3 mb-0 text-left">
                    <tbody>
                        <tr>
                            <th>Avatar Field</th>
                            <td>{{ $adminUser->avatar ?: 'Empty' }}</td>
                        </tr>
                        <tr>
                            <th>Use Gravatar</th>
                            <td>{{ $adminUser->use_gravatar ? 'Yes' : 'No' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Avatar Controls</h3>
            </div>
            <form method="POST" action="{{ route('admin.admincenter.adminusers.update', $adminUser) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="_section" value="avatar">
                <div class="card-body">
                    <div class="form-group">
                        <label for="avatar">Uploaded Avatar</label>
                        <input id="avatar" data-avatar-file type="file" name="avatar" class="form-control-file @error('avatar') is-invalid @enderror" accept="image/*">
                        <small class="form-text text-muted">Max {{ number_format(config('media_storage.avatar.max_kilobytes', 5120) / 1024) }} MB.</small>
                        @error('avatar')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="use_gravatar" value="0">
                            <input id="use_gravatar_avatar_tab" data-gravatar-toggle type="checkbox" name="use_gravatar" value="1" class="custom-control-input" @checked(old('use_gravatar', $adminUser->use_gravatar))>
                            <label class="custom-control-label" for="use_gravatar_avatar_tab">Use Gravatar</label>
                        </div>
                    </div>

                    <dl class="row mb-0">
                        <dt class="col-sm-5">Saved Avatar Field</dt>
                        <dd class="col-sm-7">{{ $adminUser->avatar ?: 'Empty' }}</dd>
                        <dt class="col-sm-5">Saved Gravatar Preference</dt>
                        <dd class="col-sm-7">{{ $adminUser->use_gravatar ? 'On' : 'Off' }}</dd>
                        <dt class="col-sm-5">Selected Source</dt>
                        <dd class="col-sm-7"><span data-avatar-source-inline>{{ ucfirst($adminUser->avatar_source) }}</span></dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save Avatar Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
@else
    <div class="alert alert-warning mb-0">You do not have permission to manage admin user avatars.</div>
@endcan
