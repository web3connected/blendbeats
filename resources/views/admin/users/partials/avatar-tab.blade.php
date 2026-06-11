@if (session('status') && session('status_tab') === 'avatar')
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any() && $activeTab === 'avatar')
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row">
    <div class="col-lg-6">
        <div class="card bg-dark">
            <div class="card-header">
                <h3 class="card-title">Avatar Preview</h3>
            </div>
            <div class="card-body text-center">
                <div class="admin-avatar-preview mx-auto mb-3">
                    <img data-avatar-image src="" alt="{{ $user->name }}" class="img-circle elevation-2">
                    <div data-avatar-initials class="admin-avatar-initials"></div>
                </div>
                <h5 class="mb-1">{{ $user->name }}</h5>
                <p class="text-muted mb-2">{{ $user->email }}</p>
                <p class="mb-1">
                    <strong data-avatar-source>
                        {{ $user->avatar ? 'Uploaded Avatar' : ($user->usesGravatar() ? 'Gravatar' : 'Generated Initial Avatar') }}
                    </strong>
                </p>
                <table class="table table-sm table-dark mt-3 mb-0 text-left">
                    <tbody>
                        <tr><th>Uploaded Avatar</th><td>{{ $user->avatar ? 'Yes' : 'No' }}</td></tr>
                        <tr><th>Gravatar</th><td>{{ $user->usesGravatar() ? 'On' : 'Off' }}</td></tr>
                        <tr><th>Generated Initials</th><td>{{ $user->getInitials() }}</td></tr>
                        <tr><th>Stored Path</th><td>{{ $user->avatar ?: 'Empty' }}</td></tr>
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
            <form method="POST" action="{{ route('admin.users.update', $user) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="_section" value="avatar">
                <div class="card-body">
                    <div class="form-group">
                        <label for="avatar">Uploaded Avatar</label>
                        <input id="avatar" data-avatar-file type="file" name="avatar" class="form-control-file @error('avatar') is-invalid @enderror" accept="image/*">
                        @error('avatar') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="use_gravatar" value="0">
                            <input id="use_gravatar_avatar_tab" data-gravatar-toggle type="checkbox" name="use_gravatar" value="1" class="custom-control-input" @checked(old('use_gravatar', $user->usesGravatar()))>
                            <label class="custom-control-label" for="use_gravatar_avatar_tab">Use Gravatar</label>
                        </div>
                    </div>

                    <dl class="row mb-0">
                        <dt class="col-sm-5">Selected Source</dt>
                        <dd class="col-sm-7">
                            <span data-avatar-source-inline>
                                {{ $user->avatar ? 'Uploaded Avatar' : ($user->usesGravatar() ? 'Gravatar' : 'Generated Initial Avatar') }}
                            </span>
                        </dd>
                        <dt class="col-sm-5">Saved Gravatar</dt>
                        <dd class="col-sm-7">{{ $user->usesGravatar() ? 'On' : 'Off' }}</dd>
                        <dt class="col-sm-5">Stored Avatar Path</dt>
                        <dd class="col-sm-7">{{ $user->avatar ?: 'Empty' }}</dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save Avatar Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
