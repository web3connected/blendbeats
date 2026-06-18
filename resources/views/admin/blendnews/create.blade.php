@extends('admin.layouts.app', [
    'title' => 'Create BlendNews Story',
    'heading' => 'Create BlendNews Story',
    'subtitle' => 'Create a draft, review item, or published news story.',
])

@section('admin_content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-plus mr-1"></i> Story Details
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.blendnews.store') }}">
                @include('admin.blendnews.partials.form')
            </form>
        </div>
    </div>
@endsection
