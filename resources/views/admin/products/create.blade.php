@extends('admin.layouts.app', [
    'title' => 'Create Product',
    'heading' => 'Create Product',
    'subtitle' => 'Add a product and define how checkout should route it.',
])

@section('admin_content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Product Details</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.products.store') }}">
                @include('admin.products.partials.form')
            </form>
        </div>
    </div>
@endsection
