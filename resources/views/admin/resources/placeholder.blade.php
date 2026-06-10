@extends('admin.layouts.app', [
    'title' => $resource['title'],
    'heading' => $resource['title'],
    'subtitle' => $resource['description'],
])

@section('admin_content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <span class="btn btn-primary btn-lg disabled mr-3">
                    <i class="{{ $resource['icon'] }}"></i>
                </span>
                <div>
                    <h2 class="h5 mb-1">{{ $resource['title'] }}</h2>
                    <p class="text-muted mb-0">{{ $resource['description'] }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
