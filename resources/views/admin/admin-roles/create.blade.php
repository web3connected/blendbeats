@extends('admin.layouts.app', [
    'title' => 'Create Admin Role',
    'heading' => 'Create Admin Role',
    'subtitle' => 'Add a new administrative role.',
])

@section('admin_content')
    <div class="card">
        <form method="POST" action="{{ route('admin.admincenter.adminroles.store') }}">
            <div class="card-body">
                @include('admin.admin-roles.partials.form')
            </div>
        </form>
    </div>
@endsection
