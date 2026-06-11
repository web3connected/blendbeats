@extends('admin.layouts.app', [
    'title' => 'Create Admin User',
    'heading' => 'Create Admin User',
    'subtitle' => 'Add a new administrator account.',
])

@section('admin_content')
    <div class="card">
        <form method="POST" action="{{ route('admin.admincenter.adminusers.store') }}">
            @csrf
            <div class="card-body">
                @include('admin.admin-users.partials.profile-fields')

                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label for="password_confirmation">Confirm Password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Create</button>
                <a href="{{ route('admin.admincenter.adminusers.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
