@extends('admin.layouts.app', [
    'title' => 'Edit Product',
    'heading' => 'Edit Product',
    'subtitle' => $product->title,
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Product Details</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.products.update', $product) }}">
                @method('PUT')
                @include('admin.products.partials.form')
            </form>
        </div>
    </div>
@endsection
