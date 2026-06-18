@extends('admin.layouts.app', [
    'title' => 'Edit BlendNews Story',
    'heading' => 'Edit BlendNews Story',
    'subtitle' => $post->title,
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-edit mr-1"></i> Story Details
            </h3>
            <div class="card-tools">
                <a href="{{ route('news.show', ['post' => $post->slug]) }}" class="btn btn-info btn-sm" target="_blank" rel="noopener">
                    <i class="fas fa-eye mr-1"></i> View Public Story
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.blendnews.update', $post) }}">
                @method('PUT')
                @include('admin.blendnews.partials.form')
            </form>
        </div>
    </div>
@endsection
